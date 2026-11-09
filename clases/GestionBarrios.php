<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
final class GestionBarrios {
    private PDO $pdo;
    public function __construct() { $this->pdo = Conexion::getConexion('BARRIOS'); }
    private function filtro(array $params, array &$bindings): string {
        $where = [];
        $nombre = trim((string)($params['nombre'] ?? ''));
        if ($nombre !== '') { $where[] = 'b.nombre LIKE :nombre'; $bindings[':nombre'] = '%' . $nombre . '%'; }
        $localidad = (string)($params['localidad'] ?? '');
        if ($localidad !== '' && ctype_digit($localidad)) { $where[] = 'b.id_localidad = :localidad'; $bindings[':localidad'] = $localidad; }
        $estado = (string)($params['estado'] ?? '');
        if ($estado !== '' && in_array($estado, ['IMPORTADO','PENDIENTE','APROBADO','RECHAZADO'], true)) { $where[] = 'b.estado = :estado'; $bindings[':estado'] = $estado; }
        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }
    private function ejecutar(string $sql, array $bindings): PDOStatement {
        $st = $this->pdo->prepare($sql);
        foreach ($bindings as $nombre => $valor) $st->bindValue($nombre, $valor, PDO::PARAM_STR);
        $st->execute(); return $st;
    }
    public function listar(array $args): array {
        $bindings=[]; $where=$this->filtro($args,$bindings);
        $total=(int)$this->pdo->query('SELECT COUNT(*) FROM db_barrios.bar_barrios')->fetchColumn();
        $filtrados=(int)$this->ejecutar('SELECT COUNT(*) FROM db_barrios.bar_barrios b'.$where,$bindings)->fetchColumn();
        $start=max(0,min(1000000,(int)($args['start'] ?? 0)));
        $length=max(5,min(100,(int)($args['length'] ?? 10)));
        $ordenColumnas=['b.id_barrio','b.nombre','l.localidad_nombre','b.id_origen_dbgral','b.codigo_dependencia_legacy','b.estado', 'b.id_barrio', 'b.id_barrio'];
        $n=(int)($args['order'][0]['column'] ?? 1); $orden=$ordenColumnas[$n] ?? 'b.nombre';
        $dir=strtolower((string)($args['order'][0]['dir'] ?? 'asc'))==='desc'?'DESC':'ASC';
        $sql="SELECT b.id_barrio, b.nombre, b.estado, b.id_origen_dbgral, b.codigo_dependencia_legacy,
                  b.codigo_localidad_legacy, l.localidad_nombre,
                  EXISTS(SELECT 1 FROM db_barrios.bar_geometrias g WHERE g.id_barrio=b.id_barrio AND g.estado='APROBADO') AS tiene_geometria,
                  (SELECT COUNT(*) FROM db_barrios.bar_equivalencias_surc es WHERE es.id_barrio=b.id_barrio AND es.estado='VALIDADA') AS surc_validadas,
                  (SELECT COUNT(*) FROM db_barrios.bar_equivalencias_surc es WHERE es.id_barrio=b.id_barrio AND es.estado='PENDIENTE') AS surc_pendientes,
                  (SELECT COUNT(*) FROM db_barrios.bar_equivalencias_surc es WHERE es.id_barrio=b.id_barrio AND es.estado='DESCARTADA') AS surc_descartadas
              FROM db_barrios.bar_barrios b
              LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
              $where ORDER BY $orden $dir, b.id_barrio ASC LIMIT :limite OFFSET :inicio";
        $st=$this->pdo->prepare($sql);
        foreach ($bindings as $key=>$value) $st->bindValue($key,$value,PDO::PARAM_STR);
        $st->bindValue(':limite',$length,PDO::PARAM_INT);
        $st->bindValue(':inicio',$start,PDO::PARAM_INT);
        $st->execute();
        return ['draw'=>(int)($args['draw'] ?? 0),'recordsTotal'=>$total,'recordsFiltered'=>$filtrados,'data'=>$st->fetchAll(PDO::FETCH_ASSOC)];
    }
    public function sugerencias(string $q): array {
        $stmt=$this->pdo->prepare("SELECT b.id_barrio, b.nombre, b.id_localidad, l.localidad_nombre
            FROM db_barrios.bar_barrios b LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
            WHERE b.nombre LIKE :q ORDER BY b.nombre,l.localidad_nombre,b.id_barrio LIMIT 20");
        $stmt->execute([':q'=>'%'.$q.'%']);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function localidades(): array {
        return $this->pdo->query("SELECT DISTINCT b.id_localidad AS id, l.localidad_nombre AS nombre
                 FROM db_barrios.bar_barrios b JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
                 WHERE b.id_localidad IS NOT NULL ORDER BY l.localidad_nombre, b.id_localidad")->fetchAll(PDO::FETCH_ASSOC);
    }
    public function detalle(int $id): ?array {
        $st=$this->pdo->prepare("SELECT b.id_barrio,b.nombre,b.estado,b.activo,b.id_origen_dbgral,
              b.id_localidad,b.codigo_localidad_legacy,b.codigo_dependencia_legacy,
              b.clasificacion,b.descripcion,l.localidad_nombre
              FROM db_barrios.bar_barrios b LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
              WHERE b.id_barrio=:id LIMIT 1");
        $st->execute([':id'=>$id]); $b=$st->fetch(PDO::FETCH_ASSOC);
        if (!$b) return null;
        $st=$this->pdo->prepare('SELECT gen_organismos_id, es_actual, fecha_desde, fecha_hasta FROM db_barrios.bar_jurisdicciones WHERE id_barrio=:id ORDER BY id_jurisdiccion DESC');
        $st->execute([':id'=>$id]); $b['jurisdicciones']=$st->fetchAll(PDO::FETCH_ASSOC);
        $st=$this->pdo->prepare('SELECT e.barrio_codigo_surc, e.localidad_codigo_surc, e.estado, s.Barrio_Descrip AS nombre_surc, s.Localidad_Codigo AS localidad_surc
            FROM db_barrios.bar_equivalencias_surc e
            LEFT JOIN dbseg.ref_barrio s ON s.Barrio_Codigo=e.barrio_codigo_surc
            WHERE e.id_barrio=:id ORDER BY e.barrio_codigo_surc');
        $st->execute([':id'=>$id]); $b['equivalencias_surc']=$st->fetchAll(PDO::FETCH_ASSOC);
        $st=$this->pdo->prepare("SELECT version, origen, estado FROM db_barrios.bar_geometrias WHERE id_barrio=:id ORDER BY version DESC");
        $st->execute([':id'=>$id]); $b['geometrias']=$st->fetchAll(PDO::FETCH_ASSOC);
        return $b;
    }
}
