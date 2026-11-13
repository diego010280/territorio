<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
final class GestionBarrios {
    private PDO $pdo;
    public function __construct() { $this->pdo = Conexion::getConexion('BARRIOS'); }

    private const REL_APROBADA = "EXISTS (SELECT 1 FROM db_barrios.bar_relaciones_barrios rg WHERE rg.id_barrio_origen=b.id_barrio AND rg.estado='APROBADA')";
    private function vista(array $args): string {
        $v=(string)($args['vista']??'principales');
        return in_array($v,['principales','historicos','todos'],true)?$v:'principales';
    }
    private function filtro(array $args, array &$bind, bool $incluirOtros=true): string {
        $where=[];
        $v=$this->vista($args);
        if($v==='principales')$where[]='NOT '.self::REL_APROBADA;
        elseif($v==='historicos')$where[]=self::REL_APROBADA;
        if($incluirOtros){
          $nombre=trim((string)($args['nombre']??''));
          if($nombre!=='') {
            $where[]="(b.nombre LIKE :nombre OR (".
              ($v==='historicos'?'0=1':"EXISTS (SELECT 1 FROM db_barrios.bar_relaciones_barrios rh JOIN db_barrios.bar_barrios bh ON bh.id_barrio=rh.id_barrio_origen WHERE rh.id_barrio_destino=b.id_barrio AND rh.estado='APROBADA' AND bh.nombre LIKE :nombreHistorico)")."))";
            $bind[':nombre']='%'.$nombre.'%';
            if($v!=='historicos')$bind[':nombreHistorico']='%'.$nombre.'%';
          }
          $localidad=(string)($args['localidad']??'');
          if($localidad!==''&&ctype_digit($localidad)) { $where[]='b.id_localidad=:localidad';$bind[':localidad']=$localidad; }
          $estado=(string)($args['estado']??'');
          if(in_array($estado,['IMPORTADO','PENDIENTE','APROBADO','RECHAZADO'],true)) { $where[]='b.estado=:estado';$bind[':estado']=$estado; }
          $id=(string)($args['id_barrio']??'');
          if($id!==''&&ctype_digit($id)){$where[]='b.id_barrio=:id_barrio';$bind[':id_barrio']=$id;}
        }
        return $where?' WHERE '.implode(' AND ',$where):'';
    }
    private function ejecutar(string $sql,array $bind): PDOStatement {
      $st=$this->pdo->prepare($sql);
      foreach($bind as $key=>$val)$st->bindValue($key,$val,PDO::PARAM_STR);
      $st->execute();return $st;
    }
    public function listar(array $args):array {
      $base=[]; $filtroBase=$this->filtro($args,$base,false);
      $bind=[];$where=$this->filtro($args,$bind);
      $total=(int)$this->ejecutar('SELECT COUNT(*) FROM db_barrios.bar_barrios b'.$filtroBase,$base)->fetchColumn();
      $filtrados=(int)$this->ejecutar('SELECT COUNT(*) FROM db_barrios.bar_barrios b'.$where,$bind)->fetchColumn();
      $start=max(0,min(1000000,(int)($args['start']??0)));
      $length=max(5,min(100,(int)($args['length']??10)));
      $orders=['b.id_barrio','b.nombre','l.localidad_nombre','b.id_origen_dbgral','b.codigo_dependencia_legacy','b.estado','b.id_barrio','b.id_barrio','b.id_barrio'];
      $n=(int)($args['order'][0]['column']??1);$order=$orders[$n]??'b.nombre';
      $dir=strtolower((string)($args['order'][0]['dir']??'asc'))==='desc'?'DESC':'ASC';
      $sql="SELECT b.id_barrio,b.nombre,b.estado,b.activo,b.id_origen_dbgral,b.codigo_dependencia_legacy,
          b.codigo_localidad_legacy,l.localidad_nombre,
          (SELECT MIN(r.id_barrio_destino) FROM db_barrios.bar_relaciones_barrios r WHERE r.id_barrio_origen=b.id_barrio AND r.estado='APROBADA') AS id_gobernante,
          EXISTS(SELECT 1 FROM db_barrios.bar_geometrias g WHERE g.id_barrio=b.id_barrio AND g.estado='APROBADO') AS tiene_geometria,
          (SELECT COUNT(*) FROM db_barrios.bar_equivalencias_surc es WHERE es.id_barrio=b.id_barrio AND es.estado='VALIDADA') AS surc_validadas,
          (SELECT COUNT(*) FROM db_barrios.bar_equivalencias_surc es WHERE es.id_barrio=b.id_barrio AND es.estado='PENDIENTE') AS surc_pendientes,
          (SELECT COUNT(*) FROM db_barrios.bar_equivalencias_surc es WHERE es.id_barrio=b.id_barrio AND es.estado='DESCARTADA') AS surc_descartadas,
          (SELECT COUNT(*) FROM db_barrios.bar_relaciones_barrios r WHERE r.id_barrio_destino=b.id_barrio AND r.estado='APROBADA') AS total_historicos
          FROM db_barrios.bar_barrios b
          LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
          $where ORDER BY $order $dir,b.id_barrio LIMIT :limite OFFSET :inicio";
      $st=$this->pdo->prepare($sql);
      foreach($bind as $k=>$v)$st->bindValue($k,$v,PDO::PARAM_STR);
      $st->bindValue(':limite',$length,PDO::PARAM_INT);$st->bindValue(':inicio',$start,PDO::PARAM_INT);
      $st->execute();
      return ['draw'=>(int)($args['draw']??0),'recordsTotal'=>$total,'recordsFiltered'=>$filtrados,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)];
    }
    public function sugerencias(string $q,string $vista='principales'):array {
      $q=trim($q);if(mb_strlen($q)<2)return [];
      $vista=in_array($vista,['principales','historicos','todos'],true)?$vista:'principales';
      // En vista principal: sólo gobernantes/no consolidados; buscar también por los alias históricos.
      if($vista==='principales') {
        $sql="SELECT b.id_barrio,b.nombre,b.id_localidad,l.localidad_nombre,
           (SELECT GROUP_CONCAT(DISTINCT bh.nombre ORDER BY bh.nombre SEPARATOR ' / ')
              FROM db_barrios.bar_relaciones_barrios r JOIN db_barrios.bar_barrios bh ON bh.id_barrio=r.id_barrio_origen
              WHERE r.id_barrio_destino=b.id_barrio AND r.estado='APROBADA' AND bh.nombre LIKE :alias) AS coincidencia_historica
           FROM db_barrios.bar_barrios b
           LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
           WHERE NOT ".self::REL_APROBADA." AND
            (b.nombre LIKE :q OR EXISTS(SELECT 1 FROM db_barrios.bar_relaciones_barrios rh
               JOIN db_barrios.bar_barrios h ON h.id_barrio=rh.id_barrio_origen
               WHERE rh.id_barrio_destino=b.id_barrio AND rh.estado='APROBADA' AND h.nombre LIKE :alias2))
           ORDER BY b.nombre,l.localidad_nombre,b.id_barrio LIMIT 20";
        $bind=[':q'=>'%'.$q.'%',':alias'=>'%'.$q.'%',':alias2'=>'%'.$q.'%'];
      } else {
        $extra=$vista==='historicos'?' AND '.self::REL_APROBADA:'';
        $sql="SELECT b.id_barrio,b.nombre,b.id_localidad,l.localidad_nombre,NULL AS coincidencia_historica
              FROM db_barrios.bar_barrios b LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
              WHERE b.nombre LIKE :q $extra ORDER BY b.nombre,l.localidad_nombre,b.id_barrio LIMIT 20";
        $bind=[':q'=>'%'.$q.'%'];
      }
      return $this->ejecutar($sql,$bind)->fetchAll(PDO::FETCH_ASSOC);
    }
    public function localidades():array {
      return $this->pdo->query("SELECT DISTINCT b.id_localidad AS id,l.localidad_nombre AS nombre
          FROM db_barrios.bar_barrios b JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
          WHERE b.id_localidad IS NOT NULL ORDER BY l.localidad_nombre,b.id_localidad")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function detalle(int $id):?array {
      $st=$this->pdo->prepare("SELECT b.id_barrio,b.nombre,b.estado,b.activo,b.id_origen_dbgral,
          b.id_localidad,b.codigo_localidad_legacy,b.codigo_dependencia_legacy,
          b.clasificacion,b.descripcion,l.localidad_nombre
          FROM db_barrios.bar_barrios b LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
          WHERE b.id_barrio=:id LIMIT 1");
      $st->execute([':id'=>$id]);$b=$st->fetch(PDO::FETCH_ASSOC);
      if(!$b)return null;
      $st=$this->pdo->prepare("SELECT r.id_barrio_origen AS id_barrio,bh.nombre,r.tipo_relacion,r.estado,
          bh.id_origen_dbgral,bh.codigo_dependencia_legacy
          FROM db_barrios.bar_relaciones_barrios r JOIN db_barrios.bar_barrios bh ON bh.id_barrio=r.id_barrio_origen
          WHERE r.id_barrio_destino=:id AND r.estado='APROBADA' ORDER BY bh.nombre");
      $st->execute([':id'=>$id]);$b['historicos_asociados']=$st->fetchAll(PDO::FETCH_ASSOC);
      $st=$this->pdo->prepare("SELECT r.id_barrio_destino AS id_barrio,bg.nombre
          FROM db_barrios.bar_relaciones_barrios r JOIN db_barrios.bar_barrios bg ON bg.id_barrio=r.id_barrio_destino
          WHERE r.id_barrio_origen=:id AND r.estado='APROBADA' ORDER BY r.id_barrio_destino");
      $st->execute([':id'=>$id]);$b['gobernantes']=$st->fetchAll(PDO::FETCH_ASSOC);
      $st=$this->pdo->prepare('SELECT j.gen_organismos_id,j.es_actual,j.fecha_desde,j.fecha_hasta,r.GEN_Organismos_Descripcion AS nombre_dependencia
        FROM db_barrios.bar_jurisdicciones j LEFT JOIN rrhh.gen_organismos r ON r.GEN_ORGANISMOS_Id=j.gen_organismos_id
        WHERE j.id_barrio=:id ORDER BY j.id_jurisdiccion DESC');
      $st->execute([':id'=>$id]);$b['jurisdicciones']=$st->fetchAll(PDO::FETCH_ASSOC);
      $st=$this->pdo->prepare('SELECT e.barrio_codigo_surc,e.localidad_codigo_surc,e.estado,s.Barrio_Descrip AS nombre_surc,s.Localidad_Codigo AS localidad_surc
        FROM db_barrios.bar_equivalencias_surc e LEFT JOIN dbseg.ref_barrio s ON s.Barrio_Codigo=e.barrio_codigo_surc
        WHERE e.id_barrio=:id ORDER BY e.barrio_codigo_surc');
      $st->execute([':id'=>$id]);$b['equivalencias_surc']=$st->fetchAll(PDO::FETCH_ASSOC);
      $st=$this->pdo->prepare('SELECT version,origen,estado FROM db_barrios.bar_geometrias WHERE id_barrio=:id ORDER BY version DESC');
      $st->execute([':id'=>$id]);$b['geometrias']=$st->fetchAll(PDO::FETCH_ASSOC);
      return $b;
    }
}
