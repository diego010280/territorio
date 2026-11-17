<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';

/** Lectura central, sin SQL construido con nombres de columna proporcionados por el cliente. */
final class AuditoriaConsultaTerritorio {
    private PDO $db;
    public function __construct(){
        $this->db=Conexion::getConexion('BARRIOS');
        $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function conexion(): PDO {return $this->db;}
    public static function filtros(array $f):array {
        $tipo=($f['vista']??'abm')==='acciones'?'acciones':'abm';
        $desde=trim((string)($f['desde']??'')); $hasta=trim((string)($f['hasta']??''));
        foreach (['desde'=>$desde,'hasta'=>$hasta] as $v) {
            if ($v!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v) || !checkdate((int)substr($v,5,2),(int)substr($v,8,2),(int)substr($v,0,4))))
                throw new InvalidArgumentException('Fecha inválida',400);
        }
        if($desde!=='' && $hasta!=='' && $desde>$hasta)throw new InvalidArgumentException('Rango de fechas inválido',400);
        $usuario=trim((string)($f['usuario']??''));
        if($usuario!=='' && (!ctype_digit($usuario) || strlen($usuario)>10))throw new InvalidArgumentException('Usuario inválido',400);
        $tipacc=trim((string)($f['tipo']??''));
        if($tipacc!=='' && (!ctype_digit($tipacc)||strlen($tipacc)>3))throw new InvalidArgumentException('Tipo de acción inválido',400);
        return [
            'vista'=>$tipo,'desde'=>$desde,'hasta'=>$hasta,'usuario'=>$usuario,'tipo'=>$tipacc,
            'tabla'=>substr(trim((string)($f['tabla']??'')),0,150),
            'registro'=>substr(trim((string)($f['registro']??'')),0,100),
            'texto'=>substr(trim((string)($f['texto']??'')),0,120),
        ];
    }
    private function especificacion(string $vista):array {
        if($vista==='abm')return [
            'base'=>'geoterr.geo_audit_abm a', 'id'=>'a.geo_audit_id','fecha'=>'a.geo_audit_fecha_evento',
            'usuario'=>'a.geo_audit_idusu','tipo'=>'a.geo_audit_idtipacc',
            'tabla'=>'a.geo_audit_tabla_afectada','registro'=>'a.geo_audit_registro_id',
            'texto'=>'a.geo_audit_observacion',
            'campos'=>'a.geo_audit_id AS id,a.geo_audit_fecha_evento AS fecha,a.geo_audit_idusu AS usuario_id,a.geo_audit_idmod AS modulo_id,t.codigo AS tipo,a.geo_audit_tabla_afectada AS tabla,a.geo_audit_registro_id AS registro,a.geo_audit_observacion AS observacion,a.geo_audit_correlacion_id AS correlacion,a.geo_audit_ip AS ip'
        ];
        return ['base'=>'geoterr.geo_audit_accion a','id'=>'a.geo_audit_accion_id',
            'fecha'=>'a.geo_audit_accion_fecha_evento','usuario'=>'a.geo_audit_accion_idusu',
            'tipo'=>'a.geo_audit_accion_idtipacc','tabla'=>null,'registro'=>null,
            'texto'=>'a.geo_audit_accion_observ',
            'campos'=>'a.geo_audit_accion_id AS id,a.geo_audit_accion_fecha_evento AS fecha,a.geo_audit_accion_idusu AS usuario_id,a.geo_audit_accion_idmod AS modulo_id,t.codigo AS tipo,NULL AS tabla,NULL AS registro,a.geo_audit_accion_observ AS observacion,NULL AS correlacion,a.geo_audit_accion_ip AS ip'];
    }
    private function condiciones(array $f,array $s,array &$params):string {
        $w=[];
        if($f['desde']!==''){$w[]=$s['fecha'].' >= :desde';$params[':desde']=$f['desde'].' 00:00:00';}
        if($f['hasta']!==''){$w[]=$s['fecha'].' < DATE_ADD(:hasta, INTERVAL 1 DAY)';$params[':hasta']=$f['hasta'];}
        if($f['usuario']!==''){$w[]=$s['usuario'].' = :usuario';$params[':usuario']=(int)$f['usuario'];}
        if($f['tipo']!==''){$w[]=$s['tipo'].' = :tipo';$params[':tipo']=(int)$f['tipo'];}
        if($f['vista']==='abm'){
            if($f['tabla']!==''){$w[]=$s['tabla'].' LIKE :tabla';$params[':tabla']='%'.$f['tabla'].'%';}
            if($f['registro']!==''){$w[]=$s['registro'].' = :registro';$params[':registro']=$f['registro'];}
        }
        if($f['texto']!==''){$w[]=$s['texto'].' LIKE :texto';$params[':texto']='%'.$f['texto'].'%';}
        return $w?' WHERE '.implode(' AND ',$w):'';
    }
    private function ejecutar(string $sql,array $params=[]):PDOStatement {
        $st=$this->db->prepare($sql);
        foreach($params as $k=>$v)$st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
        $st->execute();return $st;
    }
    public function tipos():array {return $this->db->query('SELECT id_tipo,codigo,descripcion FROM geoterr.geo_audit_tipo ORDER BY id_tipo')->fetchAll(PDO::FETCH_ASSOC);}
    public function listar(array $f,int $inicio,int $limite):array {
        $s=$this->especificacion($f['vista']);$params=[];$where=$this->condiciones($f,$s,$params);
        $join=' INNER JOIN geoterr.geo_audit_tipo t ON t.id_tipo='.$s['tipo'];
        $total=(int)$this->db->query('SELECT COUNT(*) FROM '.$s['base'])->fetchColumn();
        $filtrados=(int)$this->ejecutar('SELECT COUNT(*) FROM '.$s['base'].$where,$params)->fetchColumn();
        $sql='SELECT '.$s['campos'].' FROM '.$s['base'].$join.$where.' ORDER BY '.$s['id'].' DESC LIMIT :limite OFFSET :inicio';
        $st=$this->db->prepare($sql);foreach($params as $k=>$v)$st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);
        $st->bindValue(':limite',$limite,PDO::PARAM_INT);$st->bindValue(':inicio',$inicio,PDO::PARAM_INT);$st->execute();
        return ['recordsTotal'=>$total,'recordsFiltered'=>$filtrados,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)];
    }
    public function detalle(string $vista,int $id):array {
        if($vista==='abm'){
            $st=$this->ejecutar('SELECT a.geo_audit_id AS id,t.codigo AS tipo,a.geo_audit_idusu AS usuario_id,a.geo_audit_idmod AS modulo_id,a.geo_audit_tabla_afectada AS tabla,a.geo_audit_registro_id AS registro,a.geo_audit_correlacion_id AS correlacion,a.geo_audit_datos_antes AS antes,a.geo_audit_datos_despues AS despues,a.geo_audit_observacion AS observacion,a.geo_audit_ip AS ip,a.geo_audit_user_agent AS user_agent,a.geo_audit_fecha_evento AS fecha FROM geoterr.geo_audit_abm a JOIN geoterr.geo_audit_tipo t ON t.id_tipo=a.geo_audit_idtipacc WHERE a.geo_audit_id=:id LIMIT 1',[':id'=>$id]);
        }else{
            $st=$this->ejecutar('SELECT a.geo_audit_accion_id AS id,t.codigo AS tipo,a.geo_audit_accion_idusu AS usuario_id,a.geo_audit_accion_idmod AS modulo_id,a.geo_audit_accion_observ AS observacion,a.geo_audit_accion_param AS parametros,a.geo_audit_accion_ip AS ip,a.geo_audit_accion_user_agent AS user_agent,a.geo_audit_accion_fecha_evento AS fecha FROM geoterr.geo_audit_accion a JOIN geoterr.geo_audit_tipo t ON t.id_tipo=a.geo_audit_accion_idtipacc WHERE a.geo_audit_accion_id=:id LIMIT 1',[':id'=>$id]);
        }
        $row=$st->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Registro inexistente',404);
        return $row;
    }
    public function exportar(array $f,int $limite):array {
        $s=$this->especificacion($f['vista']);$params=[];$where=$this->condiciones($f,$s,$params);
        $join=' INNER JOIN geoterr.geo_audit_tipo t ON t.id_tipo='.$s['tipo'];
        $sql='SELECT '.$s['campos'].' FROM '.$s['base'].$join.$where.' ORDER BY '.$s['id'].' DESC LIMIT :limite';
        $st=$this->db->prepare($sql);foreach($params as $k=>$v)$st->bindValue($k,$v,is_int($v)?PDO::PARAM_INT:PDO::PARAM_STR);$st->bindValue(':limite',$limite,PDO::PARAM_INT);$st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
