<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
final class JurisdiccionesConsolidacion {
    private PDO $db;
    public function __construct(){ $this->db=Conexion::getConexion('BARRIOS'); $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    // RRHH es la única fuente de nombres, categorías y actividad: no hay catálogo duplicado.
    public function buscar(string $q): array {
        $q=trim($q);
        if(mb_strlen($q)<2||mb_strlen($q)>100)
            throw new InvalidArgumentException('Escribí entre 2 y 100 caracteres.',400);
        $s=$this->db->prepare("SELECT
              GEN_ORGANISMOS_Id AS id_dependencia,
              GEN_Organismos_Descripcion AS descripcion,
              GEN_Organismos_categ_Id AS categoria_id,
              CASE GEN_Organismos_categ_Id WHEN 52 THEN 'COMISARIA'
                  WHEN 53 THEN 'SUBCOMISARIA' WHEN 54 THEN 'DESTACAMENTO' END AS categoria_nombre
            FROM rrhh.gen_organismos
            WHERE GEN_Organismos_Activo='1'
              AND GEN_Organismos_categ_Id IN (52,53,54)
              AND (GEN_Organismos_Descripcion LIKE :q OR CAST(GEN_ORGANISMOS_Id AS CHAR)=:codigo)
            ORDER BY GEN_Organismos_categ_Id,GEN_Organismos_Descripcion LIMIT 35");
        $s->execute([':q'=>'%'.$q.'%',':codigo'=>$q]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    /** Resolución informativa, nunca transforma por sí misma una jurisdicción histórica en vigente. */
    private function sucesoresHistorico(int $id): array {
        $s=$this->db->prepare("SELECT
            ex.gen_org_id AS id_actual,
            a.GEN_Organismos_Descripcion AS nombre_actual,
            a.GEN_Organismos_categ_Id AS categoria_actual,
            a.GEN_Organismos_Activo AS activo_actual,
            ex.gen_org_estado
          FROM rrhh.gen_organismos_ex ex
          LEFT JOIN rrhh.gen_organismos a ON a.GEN_ORGANISMOS_Id=ex.gen_org_id
          WHERE ex.gen_org_exid=:id
          ORDER BY (a.GEN_Organismos_Activo='1') DESC,ex.gen_org_id");
        $s->execute([':id'=>$id]);
        $candidatos=$s->fetchAll(PDO::FETCH_ASSOC);
        foreach($candidatos as &$c){
            $c['es_seleccionable']=((string)$c['activo_actual']==='1') &&
                in_array((int)$c['categoria_actual'],[52,53,54],true);
        }
        unset($c);
        return $candidatos;
    }
    private function cabecera(int $id, bool $lock=false):array {
        $s=$this->db->prepare('SELECT id_consolidacion,nombre_gobernante,estado,usuario_solicitante
          FROM db_barrios.bar_consolidaciones WHERE id_consolidacion=:id'.($lock?' FOR UPDATE':''));
        $s->execute([':id'=>$id]); $r=$s->fetch(PDO::FETCH_ASSOC);
        if(!$r)throw new RuntimeException('Consolidación inexistente.',404);
        return $r;
    }
    public function detalle(int $id):array {
        $c=$this->cabecera($id);
        $s=$this->db->prepare("SELECT j.gen_organismos_id,j.cobertura,j.estado,j.observaciones,
              o.GEN_Organismos_Descripcion AS descripcion,
              CASE o.GEN_Organismos_categ_Id WHEN 52 THEN 'COMISARIA'
                WHEN 53 THEN 'SUBCOMISARIA' WHEN 54 THEN 'DESTACAMENTO'
                ELSE 'CATEGORIA NO HABILITADA' END AS categoria_nombre,
              o.GEN_Organismos_Activo AS activo
          FROM db_barrios.bar_consolidaciones_jurisdicciones j
          LEFT JOIN rrhh.gen_organismos o ON o.GEN_ORGANISMOS_Id=j.gen_organismos_id
          WHERE j.id_consolidacion=:id ORDER BY o.GEN_Organismos_Descripcion");
        $s->execute([':id'=>$id]);
        $actuales=$s->fetchAll(PDO::FETCH_ASSOC);
        // Los antecedentes de db_gral se muestran, pero NUNCA se asignan automáticamente.
        $s=$this->db->prepare('SELECT DISTINCT j.gen_organismos_id AS id_historico,
          IFNULL(o.GEN_Organismos_Descripcion,\'Sin nombre RRHH\') AS nombre_historico
          FROM db_barrios.bar_consolidaciones_origen co
          JOIN db_barrios.bar_jurisdicciones j ON j.id_barrio=co.id_barrio_origen
          LEFT JOIN rrhh.gen_organismos o ON o.GEN_ORGANISMOS_Id=j.gen_organismos_id
          WHERE co.id_consolidacion=:id AND j.origen=\'DB_GRAL\'
          ORDER BY j.gen_organismos_id');
        $s->execute([':id'=>$id]);
        $antecedentes=$s->fetchAll(PDO::FETCH_ASSOC);
        foreach($antecedentes as &$a){
            $a['sucesores_rrhh']=$this->sucesoresHistorico((int)$a['id_historico']);
        }
        unset($a);
        return ['consolidacion'=>$c,'propuestas'=>$actuales,'antecedentes'=>$antecedentes];
    }
    public function guardar(int $id, array $items, int $usuario, string $ip, string $agente):array {
        if($usuario<1)throw new RuntimeException('Sesión inválida.',401);
        if(count($items)>20)throw new InvalidArgumentException('Máximo 20 dependencias propuestas.',400);
        $limpio=[];
        foreach($items as $i){
          if(!is_array($i))throw new InvalidArgumentException('Formato de dependencia incorrecto.',400);
          $dep=filter_var($i['id_dependencia']??null,FILTER_VALIDATE_INT);
          $cov=(string)($i['cobertura']??'POR_VERIFICAR');
          $obs=trim((string)($i['observaciones']??''));
          if(!$dep||$dep<1||!in_array($cov,['TOTAL','PARCIAL','POR_VERIFICAR'],true)||mb_strlen($obs)>500)
            throw new InvalidArgumentException('Dependencia, cobertura u observación inválida.',400);
          if(isset($limpio[$dep]))throw new InvalidArgumentException('Dependencia duplicada.',400);
          $limpio[$dep]=['id'=>$dep,'cobertura'=>$cov,'observaciones'=>$obs];
        }
        $this->db->beginTransaction();
        try{
            $c=$this->cabecera($id,true);
            if($c['estado']!=='PENDIENTE')throw new RuntimeException('Solo se pueden modificar consolidaciones pendientes.',409);
            if((int)$c['usuario_solicitante']!==$usuario)throw new RuntimeException('Solo el solicitante puede editar las jurisdicciones propuestas.',403);
            if($limpio){
              $keys=array_keys($limpio);$in=implode(',',array_fill(0,count($keys),'?'));
              $s=$this->db->prepare("SELECT GEN_ORGANISMOS_Id FROM rrhh.gen_organismos
                WHERE GEN_Organismos_Activo='1' AND GEN_Organismos_categ_Id IN (52,53,54) AND GEN_ORGANISMOS_Id IN ($in)");
              $s->execute($keys);
              if(count($s->fetchAll(PDO::FETCH_COLUMN))!==count($keys))
                throw new InvalidArgumentException('Alguna dependencia está inactiva o no pertenece a las categorías 52, 53 y 54.',400);
            }
            $s=$this->db->prepare('SELECT gen_organismos_id,cobertura,estado,observaciones FROM db_barrios.bar_consolidaciones_jurisdicciones WHERE id_consolidacion=:id');
            $s->execute([':id'=>$id]);$antes=$s->fetchAll(PDO::FETCH_ASSOC);
            $this->db->prepare('DELETE FROM db_barrios.bar_consolidaciones_jurisdicciones WHERE id_consolidacion=:id')->execute([':id'=>$id]);
            $s=$this->db->prepare("INSERT INTO db_barrios.bar_consolidaciones_jurisdicciones
              (id_consolidacion,gen_organismos_id,cobertura,estado,observaciones,usuario_registro)
              VALUES (:c,:d,:cob,'POR_VERIFICAR',:obs,:u)");
            foreach($limpio as $i) $s->execute([':c'=>$id,':d'=>$i['id'],':cob'=>$i['cobertura'],':obs'=>$i['observaciones']?:null,':u'=>$usuario]);
            $aud=$this->db->prepare('INSERT INTO geoterr.geo_auditoria
              (modulo,entidad,id_registro,accion,usuario_id,descripcion,datos_anteriores,datos_nuevos,ip,user_agent)
              VALUES (:mod,:ent,:id,:act,:uid,:desc,:old,:new,:ip,:ua)');
            $aud->execute([':mod'=>'TERRITORIO',':ent'=>'BAR_CONSOLIDACIONES_JURISDICCIONES',':id'=>$id,
              ':act'=>'MODIFICAR_PROPUESTA_JURISDICCIONES',':uid'=>$usuario,
              ':desc'=>'Actualización de jurisdicciones propuestas de consolidación '.$id,
              ':old'=>json_encode($antes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
              ':new'=>json_encode(array_values($limpio),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
              ':ip'=>substr($ip,0,45),':ua'=>substr($agente,0,250)]);
            $this->db->commit();
            return ['ok'=>true,'id_consolidacion'=>$id,'estado'=>'PENDIENTE','cantidad'=>count($limpio)];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
