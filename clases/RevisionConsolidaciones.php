<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
final class RevisionConsolidaciones {
    private PDO $db;
    public function __construct(){ $this->db = Conexion::getConexion('BARRIOS'); }
    public function listar(string $estado): array {
        if (!in_array($estado,['PENDIENTE','APROBADA','RECHAZADA','CANCELADA','TODAS'],true)) throw new InvalidArgumentException('Estado inválido.',400);
        $sql="SELECT c.id_consolidacion,c.nombre_gobernante,c.id_localidad,l.localidad_nombre,
            c.tipo_relacion,c.estado,c.fecha_solicitud,c.barrio_codigo_surc,
            c.id_importacion_municipal,c.usuario_solicitante,
            (SELECT COUNT(*) FROM db_barrios.bar_consolidaciones_origen o WHERE o.id_consolidacion=c.id_consolidacion) AS total_historicos
            FROM db_barrios.bar_consolidaciones c
            LEFT JOIN db_gral.localidades l ON l.localidad_id=c.id_localidad";
        if ($estado!=='TODAS') $sql.=' WHERE c.estado=:estado';
        $sql.=' ORDER BY c.fecha_solicitud DESC,c.id_consolidacion DESC LIMIT 250';
        $s=$this->db->prepare($sql);
        if ($estado!=='TODAS') $s->bindValue(':estado',$estado);
        $s->execute();return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function detalle(int $id):array {
        if ($id<1) throw new InvalidArgumentException('Identificador inválido.',400);
        $s=$this->db->prepare("SELECT c.*, l.localidad_nombre, m.id_externo AS codigo_municipal,
            m.nombre_original AS nombre_municipal, m.estado AS estado_municipal,
            ST_AsGeoJSON(m.geometria) AS geometria_municipal,
            s.Barrio_Descrip AS nombre_surc, s.Localidad_Codigo AS localidad_surc,
            e.id_barrio AS surc_asociado_barrio, e.estado AS estado_surc_actual
            FROM db_barrios.bar_consolidaciones c
            LEFT JOIN db_gral.localidades l ON l.localidad_id=c.id_localidad
            LEFT JOIN db_barrios.bar_importacion_geometrias m ON m.id_importacion=c.id_importacion_municipal
            LEFT JOIN dbseg.ref_barrio s ON s.Barrio_Codigo=c.barrio_codigo_surc
            LEFT JOIN db_barrios.bar_equivalencias_surc e ON e.barrio_codigo_surc=c.barrio_codigo_surc
            WHERE c.id_consolidacion=:id LIMIT 1");
        $s->execute([':id'=>$id]);$c=$s->fetch(PDO::FETCH_ASSOC);
        if(!$c)throw new RuntimeException('Consolidación inexistente.',404);
        $geo=$c['geometria_municipal'] ? json_decode($c['geometria_municipal'],true,512,JSON_THROW_ON_ERROR):null;
        unset($c['geometria_municipal']);$c['geometry']=$geo;
        $s=$this->db->prepare("SELECT o.id_barrio_origen,o.nombre_origen_snapshot,b.nombre AS nombre_actual,
             b.id_localidad,b.estado AS estado_barrio,
             (SELECT COUNT(*) FROM db_barrios.bar_relaciones_barrios r WHERE r.id_barrio_origen=o.id_barrio_origen AND r.estado='APROBADA') AS gobernantes_actuales
             FROM db_barrios.bar_consolidaciones_origen o
             JOIN db_barrios.bar_barrios b ON b.id_barrio=o.id_barrio_origen
             WHERE o.id_consolidacion=:id ORDER BY o.id_barrio_origen");
        $s->execute([':id'=>$id]);$c['historicos']=$s->fetchAll(PDO::FETCH_ASSOC);
        // Las jurisdicciones propuestas se leen SIEMPRE desde RRHH en tiempo real.
        $s=$this->db->prepare("SELECT j.gen_organismos_id,j.cobertura,j.estado,j.observaciones,
              r.GEN_Organismos_Descripcion AS dependencia,
              r.GEN_Organismos_categ_Id AS categoria, r.GEN_Organismos_Activo AS activo
              FROM db_barrios.bar_consolidaciones_jurisdicciones j
              LEFT JOIN rrhh.gen_organismos r ON r.GEN_ORGANISMOS_Id=j.gen_organismos_id
              WHERE j.id_consolidacion=:id ORDER BY j.gen_organismos_id");
        $s->execute([':id'=>$id]);
        $c['jurisdicciones']=$s->fetchAll(PDO::FETCH_ASSOC);
        $c['jurisdicciones_sin_resolver']=count(array_filter(
             $c['jurisdicciones'], fn($j)=>$j['estado']==='POR_VERIFICAR'));
        $c['controles'] = [
          'al_menos_dos_historicos'=>count($c['historicos'])>=2,
          'misma_localidad'=>count(array_filter($c['historicos'],fn($h)=>(string)$h['id_localidad']!==(string)$c['id_localidad']))===0,
          'sin_gobernante_previo'=>count(array_filter($c['historicos'],fn($h)=>(int)$h['gobernantes_actuales']>0))===0,
          'surc_no_validado_con_otro'=> $c['barrio_codigo_surc']===null || $c['surc_asociado_barrio']===null || $c['estado_surc_actual']!=='VALIDADA',
          'municipal_disponible'=> $c['id_importacion_municipal']===null || $c['geometry']!==null
        ];
        return $c;
    }
    /** Decisión individual sobre una dependencia propuesta. No declara jurisdicción vigente. */
    public function resolverJurisdiccion(int $id,int $dependencia,int $usuario,string $decision,string $fundamento):array {
        $fundamento=trim($fundamento);
        if($id<1||$dependencia<1||$usuario<1||!in_array($decision,['VALIDADA','DESCARTADA'],true)
           ||mb_strlen($fundamento)<12||mb_strlen($fundamento)>1500)
            throw new InvalidArgumentException('Requiere decisión válida y fundamento de 12 a 1500 caracteres.',400);
        $this->db->beginTransaction();
        try{
            $s=$this->db->prepare('SELECT estado,usuario_solicitante FROM db_barrios.bar_consolidaciones WHERE id_consolidacion=:id FOR UPDATE');
            $s->execute([':id'=>$id]);$c=$s->fetch(PDO::FETCH_ASSOC);
            if(!$c)throw new RuntimeException('Consolidación inexistente.',404);
            if($c['estado']!=='PENDIENTE')throw new RuntimeException('Consolidación ya resuelta.',409);
            // La autorización para resolver se verifica por perfil en el endpoint AJAX.
            // Se permite resolver una solicitud propia con permiso institucional de modificación.
            $s=$this->db->prepare('SELECT cobertura,estado,observaciones FROM db_barrios.bar_consolidaciones_jurisdicciones WHERE id_consolidacion=:id AND gen_organismos_id=:dep FOR UPDATE');
            $s->execute([':id'=>$id,':dep'=>$dependencia]);$antes=$s->fetch(PDO::FETCH_ASSOC);
            if(!$antes)throw new RuntimeException('Dependencia no propuesta en esta consolidación.',404);
            if($antes['estado']!=='POR_VERIFICAR')throw new RuntimeException('La dependencia ya tiene una decisión; requiere procedimiento de rectificación.',409);
            // La resolución se apoya en el catálogo vigente de RRHH.
            $s=$this->db->prepare('SELECT GEN_Organismos_Descripcion, GEN_Organismos_Activo, GEN_Organismos_categ_Id FROM rrhh.gen_organismos WHERE GEN_ORGANISMOS_Id=:id');
            $s->execute([':id'=>$dependencia]);$rrhh=$s->fetch(PDO::FETCH_ASSOC);
            if($decision==='VALIDADA' && (!$rrhh || (int)$rrhh['GEN_Organismos_Activo']!==1
                  || !in_array((int)$rrhh['GEN_Organismos_categ_Id'],[52,53,54],true)))
                throw new RuntimeException('La dependencia ya no está activa en una categoría policial habilitada.',409);
            $s=$this->db->prepare("UPDATE db_barrios.bar_consolidaciones_jurisdicciones SET estado=:estado WHERE id_consolidacion=:id AND gen_organismos_id=:dep AND estado='POR_VERIFICAR'");
            $s->execute([':estado'=>$decision,':id'=>$id,':dep'=>$dependencia]);
            if($s->rowCount()!==1)throw new RuntimeException('No se pudo resolver la propuesta.',409);
            $viejo=json_encode(['id_consolidacion'=>$id,'dependencia'=>$dependencia,'registro'=>$antes],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $nuevo=json_encode(['estado'=>$decision,'fundamento'=>$fundamento,'rrhh'=>$rrhh,
                'usuario_solicitante'=>(int)$c['usuario_solicitante'],
                'usuario_resolutor'=>$usuario,
                'autoaprobacion'=>((int)$c['usuario_solicitante']===$usuario)],
                JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $s=$this->db->prepare('INSERT INTO geoterr.geo_auditoria (modulo,entidad,id_registro,accion,usuario_id,descripcion,datos_anteriores,datos_nuevos,ip,user_agent) VALUES (:m,:e,:id,:a,:u,:d,:antes,:nuevos,:ip,:agente)');
            $s->execute([':m'=>'TERRITORIO',':e'=>'CONSOLIDACION_JURISDICCION',':id'=>$id,
                ':a'=>'RESOLVER_JURISDICCION',':u'=>$usuario,':d'=>$fundamento,
                ':antes'=>$viejo,':nuevos'=>$nuevo,':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),
                ':agente'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,250)]);
            $this->db->commit();
            return ['ok'=>true,'id_consolidacion'=>$id,'gen_organismos_id'=>$dependencia,'estado'=>$decision];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function rechazar(int $id,int $usuario,string $motivo):void {
        $motivo=trim($motivo);
        if($id<1 || $usuario<1 || mb_strlen($motivo)<12 || mb_strlen($motivo)>3000)
            throw new InvalidArgumentException('Indicá un fundamento de rechazo de entre 12 y 3000 caracteres.',400);
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT estado, usuario_solicitante FROM db_barrios.bar_consolidaciones WHERE id_consolidacion=:id FOR UPDATE');
            $s->execute([':id'=>$id]);$c=$s->fetch(PDO::FETCH_ASSOC);
            if(!$c)throw new RuntimeException('Consolidación inexistente.',404);
            if($c['estado']!=='PENDIENTE')throw new InvalidArgumentException('La consolidación ya fue resuelta.',409);
            // La autorización para rechazar se verifica por perfil en el endpoint AJAX.
            // El solicitante también puede resolver si posee ese permiso institucional.
            $s=$this->db->prepare("UPDATE db_barrios.bar_consolidaciones SET estado='RECHAZADA',observaciones_resolucion=:m,
              usuario_aprobador=:u,fecha_resolucion=NOW() WHERE id_consolidacion=:id AND estado='PENDIENTE'");
            $s->execute([':m'=>$motivo,':u'=>$usuario,':id'=>$id]);
            if($s->rowCount()!==1) throw new RuntimeException('No fue posible actualizar la consolidación.');
            $s=$this->db->prepare("INSERT INTO db_barrios.bar_consolidaciones_eventos
              (id_consolidacion,accion,usuario_id,detalle) VALUES (:id,'RECHAZO',:u,:d)");
            $s->execute([':id'=>$id,':u'=>$usuario,':d'=>$motivo]);
            // Auditoría central obligatoria dentro de la misma transacción.
            $antes=json_encode(['estado'=>'PENDIENTE','usuario_solicitante'=>(int)$c['usuario_solicitante']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $despues=json_encode(['estado'=>'RECHAZADA','motivo'=>$motivo,
                'usuario_resolutor'=>$usuario,
                'autoaprobacion'=>((int)$c['usuario_solicitante']===$usuario)],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $s=$this->db->prepare('INSERT INTO geoterr.geo_auditoria (modulo,entidad,id_registro,accion,usuario_id,descripcion,datos_anteriores,datos_nuevos,ip,user_agent) VALUES (:m,:e,:id,:a,:u,:d,:antes,:nuevos,:ip,:agente)');
            $s->execute([':m'=>'TERRITORIO', ':e'=>'CONSOLIDACION', ':id'=>$id,
                ':a'=>'RECHAZAR_CONSOLIDACION', ':u'=>$usuario, ':d'=>$motivo,
                ':antes'=>$antes, ':nuevos'=>$despues,
                ':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),
                ':agente'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,250)]);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
