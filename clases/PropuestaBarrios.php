<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
final class PropuestaBarrios {
    private PDO $db;
    public function __construct(){ $this->db=Conexion::getConexion('BARRIOS'); $this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); }
    public function clasificaciones():array {
        return $this->db->query('SELECT codigo, nombre FROM db_barrios.bar_clasificaciones WHERE activo=1 ORDER BY orden, nombre')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function localidades(string $q):array {
        $s=$this->db->prepare('SELECT localidad_id AS id, localidad_nombre AS nombre, departamento_id, municipio_id FROM db_gral.localidades WHERE localidad_nombre LIKE :q ORDER BY localidad_nombre,localidad_id LIMIT 40');
        $s->execute([':q'=>'%'.$q.'%']);return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function coincidencias(string $q):array {
        $sql="SELECT b.id_barrio, b.nombre, l.localidad_nombre, b.id_localidad
              FROM db_barrios.bar_barrios b
              LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad
              WHERE b.nombre LIKE :q ORDER BY b.nombre, l.localidad_nombre, b.id_barrio LIMIT 15";
        $st=$this->db->prepare($sql);$st->execute([':q'=>'%'.$q.'%']);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    /** Edición: recupera únicamente geometría oficial vigente del gobernante. */
    public function detalle(int $id):?array {
        $s=$this->db->prepare('SELECT b.id_barrio,b.id_localidad,b.nombre,b.clasificacion,b.descripcion,b.estado,l.localidad_nombre FROM db_barrios.bar_barrios b LEFT JOIN db_gral.localidades l ON l.localidad_id=b.id_localidad WHERE b.id_barrio=:id LIMIT 1');
        $s->execute([':id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
        $s=$this->db->prepare("SELECT id_barrio_destino FROM db_barrios.bar_relaciones_barrios WHERE id_barrio_origen=:id AND estado='APROBADA' ORDER BY id_relacion LIMIT 1");
        $s->execute([':id'=>$id]);$gob=$s->fetchColumn();
        if($gob)throw new RuntimeException('Este registro es histórico consolidado. Modificá el barrio gobernante ID '.$gob.'.',409);
        $s=$this->db->prepare("SELECT id_geometria,version,origen,ST_SRID(geometria) AS srid,ST_AsGeoJSON(geometria) AS geo FROM db_barrios.bar_geometrias WHERE id_barrio=:id AND estado='APROBADO' ORDER BY version DESC,id_geometria DESC LIMIT 1");
        $s->execute([':id'=>$id]);$geo=$s->fetch(PDO::FETCH_ASSOC);
        $r['geometry']=$geo?json_decode((string)$geo['geo'],true,512,JSON_THROW_ON_ERROR):null;
        $r['geometria_oficial']=$geo?[
            'id_geometria'=>(int)$geo['id_geometria'],
            'version'=>(int)$geo['version'],
            'origen'=>(string)$geo['origen'],
            'srid'=>(int)$geo['srid'],
            'estado'=>'APROBADO'
        ]:null;
        return $r;
    }
    /**
     * Busca geometrías municipales como candidatas; no las vincula con el barrio.
     */
    public function buscarMunicipales(string $q): array {
        $q = trim($q);
        if (mb_strlen($q) < 2 || mb_strlen($q) > 100) {
            throw new InvalidArgumentException('Escribí entre 2 y 100 caracteres.');
        }
        $s = $this->db->prepare(
            "SELECT id_importacion, id_externo, nombre_original, clasificacion, tipo, estado
             FROM db_barrios.bar_importacion_geometrias
             WHERE nombre_original LIKE :texto OR id_externo = :codigo
             ORDER BY nombre_original, id_importacion LIMIT 25"
        );
        $s->execute([':texto'=>'%'.$q.'%', ':codigo'=>$q]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Solo lectura: devuelve una geometría municipal sin marcarla como validada.
     */
    public function geometriaMunicipal(int $idImportacion): ?array {
        $s = $this->db->prepare(
            "SELECT id_importacion, id_externo, nombre_original, clasificacion, tipo,
                    estado, ST_AsGeoJSON(geometria) AS geojson
             FROM db_barrios.bar_importacion_geometrias
             WHERE id_importacion = :id LIMIT 1"
        );
        $s->execute([':id'=>$idImportacion]);
        $r=$s->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $r['geometry']=json_decode((string)$r['geojson'], true, 512, JSON_THROW_ON_ERROR);
        unset($r['geojson']);
        return $r;
    }

    private function cols(string $tabla):array {
        $s=$this->db->query('SHOW COLUMNS FROM db_barrios.`'.$tabla.'`');$out=[];
        foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['Field']]=$r;
        return $out;
    }
    private function campo(array $cols,array $aliases,bool $required=false):?string {
        foreach($aliases as $c){if(isset($cols[$c]))return $c;}
        if($required) throw new RuntimeException('No se encontró columna compatible para '.implode('/',$aliases).' en la estructura real. Enviar SHOW CREATE TABLE antes de habilitar guardado.');
        return null;
    }
    private function jsonGeometria($g):?string {
        if($g===null)return null;
        if(!is_array($g)||($g['type']??'')!=='MultiPolygon'||!is_array($g['coordinates']??null))throw new InvalidArgumentException('GeoJSON inválido: solo MultiPolygon.');
        $polys=$g['coordinates'];if(!$polys ||count($polys)>100)throw new InvalidArgumentException('Cantidad de polígonos inválida.');
        $total=0;$wktPolys=[];
        foreach($polys as $poly){if(!is_array($poly)||!$poly)throw new InvalidArgumentException('Polígono vacío.');$rings=[];
            foreach($poly as $ring){if(!is_array($ring)||count($ring)<4)throw new InvalidArgumentException('Anillo con menos de 4 puntos.');$total+=count($ring);if($total>30000)throw new InvalidArgumentException('Geometría demasiado grande.');$pts=[];
                foreach($ring as $p){if(!is_array($p)||count($p)<2||!is_numeric($p[0])||!is_numeric($p[1]))throw new InvalidArgumentException('Coordenadas inválidas.');$x=(float)$p[0];$y=(float)$p[1];if(!is_finite($x)||!is_finite($y)||abs($x)>180||abs($y)>90)throw new InvalidArgumentException('Coordenadas fuera de rango.');$pts[]=sprintf('%.12F %.12F',$x,$y);}
                if($pts[0]!==$pts[count($pts)-1])throw new InvalidArgumentException('Anillo no cerrado.');
                if(count(array_unique(array_slice($pts,0,-1)))<3)throw new InvalidArgumentException('Anillo sin al menos tres puntos distintos.');
                $rings[]='('.implode(',',$pts).')';
            }$wktPolys[]='('.implode(',',$rings).')';
        }
        return 'MULTIPOLYGON('.implode(',',$wktPolys).')';
    }
    public function guardar(array $d,int $usuario):int {
        $id=(int)($d['id_barrio']??0);$nombre=trim((string)($d['nombre']??''));$loc=(string)($d['id_localidad']??'');$motivo=trim((string)($d['motivo']??''));
        if($nombre===''||mb_strlen($nombre)>200||$motivo===''||mb_strlen($motivo)>2000||!ctype_digit($loc)||$loc==='0')throw new InvalidArgumentException('Nombre, localidad y motivo son obligatorios.');
        $clasificacion=trim((string)($d['clasificacion']??''));$descripcion=trim((string)($d['descripcion']??''));
        if(mb_strlen($clasificacion)>50||mb_strlen($descripcion)>5000)throw new InvalidArgumentException('Texto demasiado largo.');
        if($clasificacion!==''){
            $ver=$this->db->prepare('SELECT 1 FROM db_barrios.bar_clasificaciones WHERE codigo=:codigo AND activo=1 LIMIT 1');
            $ver->execute([':codigo'=>$clasificacion]);
            if(!$ver->fetchColumn())throw new InvalidArgumentException('La clasificación seleccionada no está habilitada en el catálogo.');
        }
        $wkt=$this->jsonGeometria($d['geometry']??null);
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT localidad_id FROM db_gral.localidades WHERE localidad_id=:id LIMIT 1');$s->execute([':id'=>$loc]);if(!$s->fetchColumn())throw new InvalidArgumentException('La localidad indicada no existe.');
            $version=0;
            if($id){
                $s=$this->db->prepare('SELECT id_barrio FROM db_barrios.bar_barrios WHERE id_barrio=:id FOR UPDATE');$s->execute([':id'=>$id]);if(!$s->fetchColumn())throw new InvalidArgumentException('Barrio inexistente.');
                // Evitar edición directa de históricos ya consolidados.
                $s=$this->db->prepare("SELECT id_barrio_destino FROM db_barrios.bar_relaciones_barrios WHERE id_barrio_origen=:id AND estado='APROBADA' LIMIT 1");
                $s->execute([':id'=>$id]);$gob=$s->fetchColumn();
                if($gob)throw new RuntimeException('Este barrio histórico pertenece al gobernante ID '.$gob.'.',409);
                // Evitar guardar propuesta basada en una versión oficial ya reemplazada.
                $s=$this->db->prepare("SELECT COALESCE(MAX(version),0) FROM db_barrios.bar_geometrias WHERE id_barrio=:id AND estado='APROBADO'");
                $s->execute([':id'=>$id]);$version=(int)$s->fetchColumn();
                if(!array_key_exists('version_base',$d) || !is_numeric($d['version_base']) || (int)$d['version_base']!==$version)
                    throw new RuntimeException('La versión oficial cambió o no fue verificada. Recargá el formulario antes de presentar.',409);
            }
            $cols=$this->cols('bar_solicitudes');
            $nom=['id_barrio','tipo_solicitud','estado','datos_propuestos'];$vals=[':barrio',':tipo',':estado',':datos'];
            $params=[':barrio'=>$id?:null,':tipo'=>$id?'MODIFICACION':'ALTA',':estado'=>'PENDIENTE',':datos'=>json_encode(['nombre'=>$nombre,'id_localidad'=>(int)$loc,'clasificacion'=>$clasificacion,'descripcion'=>$descripcion],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
            foreach([['motivo','justificacion','observaciones'],['usuario_solicitante','id_usuario_solicitante','usuario_id','usuario_creacion'],['version_base']] as $i=>$aliases){
                $c=$this->campo($cols,$aliases,$i<2);if($c){$nom[]=$c;$tag=':meta'.$i;$vals[]=$tag;$params[$tag]=[$motivo,$usuario,$version][$i];}
            }
            if(isset($cols['fecha_presentacion'])){ $nom[]='fecha_presentacion'; $vals[]='NOW()'; }
            $sql='INSERT INTO db_barrios.bar_solicitudes (`'.implode('`,`',$nom).'`) VALUES ('.implode(',',$vals).')';
            $stmt=$this->db->prepare($sql);$stmt->execute($params);$sid=(int)$this->db->lastInsertId();
            if($wkt!==null){
                // MariaDB 10.11 NO incluye ST_IsValid (disponible en versiones posteriores).
                // No sustituirla por ST_IsSimple como si fuese validación OGC completa.
                // Se verifica construcción WKT, tipo, SRID y geometría no vacía.
                // Los controles topológicos completos se efectuarán antes de aprobar/publicar.
                $verificar = $this->db->prepare(
                    'SELECT ST_GeometryType(g) AS tipo, ST_SRID(g) AS srid, ST_IsEmpty(g) AS vacia '
                    . 'FROM (SELECT ST_GeomFromText(:wkt, 4326) AS g) x'
                );
                $verificar->execute([':wkt' => $wkt]);
                $geo = $verificar->fetch(PDO::FETCH_ASSOC);
                if (!$geo || $geo['tipo'] !== 'MULTIPOLYGON' ||
                    (int)$geo['srid'] !== 4326 || (int)$geo['vacia'] !== 0) {
                    throw new InvalidArgumentException('La geometría no es un MultiPolygon válido para almacenar.');
                }
                $gcols=$this->cols('bar_solicitudes_geometrias');
                $gnom=['id_solicitud','geometria'];$gval=[':sol','ST_GeomFromText(:wkt,4326)'];$gp=[':sol'=>$sid,':wkt'=>$wkt];
                $origen=$this->campo($gcols,['origen']);if($origen){$gnom[]=$origen;$gval[]=':origen';$gp[':origen']='LEAFLET';}
                $stmt=$this->db->prepare('INSERT INTO db_barrios.bar_solicitudes_geometrias (`'.implode('`,`',$gnom).'`) VALUES ('.implode(',',$gval).')');$stmt->execute($gp);
            }
            // Auditoría central en la MISMA transacción: si falla, no queda solicitud parcial.
            $aud=$this->db->prepare('INSERT INTO geoterr.geo_auditoria (modulo,entidad,id_registro,accion,usuario_id,descripcion,datos_anteriores,datos_nuevos,ip,user_agent) VALUES (:modulo,:entidad,:id,:accion,:usuario,:descripcion,:anterior,:nuevo,:ip,:agente)');
            $aud->execute([
                ':modulo'=>'TERRITORIO', ':entidad'=>'SOLICITUD_BARRIO', ':id'=>$sid,
                ':accion'=>$id?'PROPONER_MODIFICACION':'PROPONER_ALTA', ':usuario'=>$usuario,
                ':descripcion'=>$motivo,
                ':anterior'=>json_encode(['id_barrio'=>$id?:null,'version_oficial_base'=>$version],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                ':nuevo'=>json_encode(['id_solicitud'=>$sid,'id_barrio'=>$id?:null,'estado'=>'PENDIENTE','nombre'=>$nombre,'id_localidad'=>$loc,'clasificacion'=>$clasificacion,'tiene_geometria'=>$wkt!==null],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                ':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),
                ':agente'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,250)
            ]);
            $this->db->commit();return $sid;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
