<?php
declare(strict_types=1);
/** Revisión conservadora: solo MULTIPOLYGON simple sin huecos, sin autointersecciones. */
final class RevisionJurisdicciones {
    public function __construct(private PDO $db) {}
    public function listar(): array {
        $q=$this->db->query("SELECT s.id_solicitud,s.id_jurisdiccion,s.tipo,s.estado,s.version_base,
               s.motivo,s.usuario_solicitante,s.fecha_solicitud,
               j.nombre,j.tipo AS tipo_jurisdiccion,j.codigo_externo,j.numero_ddp,
               (SELECT MIN(v.id_organismo_rrhh) FROM geoterr.geo_jurisdicciones_rrhh v
                WHERE v.id_jurisdiccion=j.id_jurisdiccion AND v.estado IN('PROPUESTO','VALIDADO')) AS id_rrhh
            FROM geoterr.geo_jurisdicciones_solicitudes s
            JOIN geoterr.geo_jurisdicciones j ON j.id_jurisdiccion=s.id_jurisdiccion
            WHERE s.estado='PENDIENTE' ORDER BY s.fecha_solicitud,s.id_solicitud LIMIT 150");
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public function detalle(int $id): array {
        $s=$this->db->prepare("SELECT s.id_solicitud,s.id_jurisdiccion,s.tipo,s.estado,s.version_base,s.motivo,s.usuario_solicitante,s.fecha_solicitud,
                j.nombre,j.tipo AS tipo_jurisdiccion,j.codigo_externo,j.numero_ddp,j.estado AS estado_jurisdiccion,
                ST_AsGeoJSON(s.geom_propuesta) AS propuesta_geojson,ST_SRID(s.geom_propuesta) AS srid_propuesta,
                (SELECT MIN(v.id_organismo_rrhh) FROM geoterr.geo_jurisdicciones_rrhh v
                 WHERE v.id_jurisdiccion=j.id_jurisdiccion AND v.estado IN('PROPUESTO','VALIDADO')) AS id_rrhh
            FROM geoterr.geo_jurisdicciones_solicitudes s
            JOIN geoterr.geo_jurisdicciones j ON j.id_jurisdiccion=s.id_jurisdiccion WHERE s.id_solicitud=? LIMIT 1");
        $s->execute([$id]);$d=$s->fetch(PDO::FETCH_ASSOC);
        if(!$d)throw new RuntimeException('Solicitud inexistente.',404);
        $g=$this->db->prepare("SELECT id_geometria,version,ST_AsGeoJSON(geometria) AS geojson
             FROM geoterr.geo_jurisdicciones_geometrias
             WHERE id_jurisdiccion=? AND estado='APROBADO' ORDER BY version DESC LIMIT 1");
        $g->execute([$d['id_jurisdiccion']]);$o=$g->fetch(PDO::FETCH_ASSOC)?:null;
        $d['propuesta']=$d['propuesta_geojson']?json_decode((string)$d['propuesta_geojson'],true):null;
        unset($d['propuesta_geojson']);
        $d['oficial']=$o?json_decode((string)$o['geojson'],true):null;
        $d['version_oficial']=$o?(int)$o['version']:0;
        return $d;
    }
    private static function cross(array $a,array $b,array $c): float {
        return ($b[0]-$a[0])*($c[1]-$a[1])-($b[1]-$a[1])*($c[0]-$a[0]);
    }
    private static function intersection(array $a,array $b,array $c,array $d): bool {
        $ab1=self::cross($a,$b,$c);$ab2=self::cross($a,$b,$d);
        $cd1=self::cross($c,$d,$a);$cd2=self::cross($c,$d,$b);
        // Colineal / contacto no adyacente: bloqueo conservador.
        if(abs($ab1)<1e-12||abs($ab2)<1e-12||abs($cd1)<1e-12||abs($cd2)<1e-12)return true;
        return ($ab1>0)!==($ab2>0) && ($cd1>0)!==($cd2>0);
    }
    private static function dentro(array $p,array $ring): bool {
        $inside=false;$n=count($ring);
        for($i=0,$j=$n-2;$i<$n-1;$j=$i++){
            $yi=(float)$ring[$i][1];$yj=(float)$ring[$j][1];
            if(($yi>(float)$p[1])!==($yj>(float)$p[1])){
                $x=(float)$ring[$i][0]+((float)$p[1]-$yi)*((float)$ring[$j][0]-(float)$ring[$i][0])/($yj-$yi);
                if((float)$p[0]<$x)$inside=!$inside;
            }
        }
        return $inside;
    }
    private static function validarGeometria(array $geom): array {
        if(($geom['type']??'')!=='MultiPolygon'||!is_array($geom['coordinates']??null))throw new RuntimeException('Solo se aceptan MULTIPOLYGON SRID 4326.',409);
        $polys=$geom['coordinates'];if(!$polys||count($polys)>25)throw new RuntimeException('Cantidad de piezas fuera de rango.',409);
        $conteo=0;
        foreach($polys as $poly){
            if(!is_array($poly)||count($poly)!==1)throw new RuntimeException('La propuesta presenta huecos o anillos complejos. Requiere revisión GIS externa.',409);
            $ring=$poly[0];if(!is_array($ring)||count($ring)<4||count($ring)>3000)throw new RuntimeException('Anillo sin suficientes vértices.',409);
            $n=count($ring);$area=0;
            for($i=0;$i<$n;$i++){
                $pt=$ring[$i];if(!is_array($pt)||count($pt)<2||!is_numeric($pt[0])||!is_numeric($pt[1]))throw new RuntimeException('Coordenada no válida.',409);
                $x=(float)$pt[0];$y=(float)$pt[1];if(!is_finite($x)||!is_finite($y)||abs($x)>180||abs($y)>90)throw new RuntimeException('Coordenada fuera de rango.',409);
            }
            if($ring[0][0]!=$ring[$n-1][0]||$ring[0][1]!=$ring[$n-1][1])throw new RuntimeException('Anillo sin cierre.',409);
            for($i=0;$i<$n-1;$i++){
                $a=$ring[$i];$b=$ring[$i+1];
                if($a[0]==$b[0]&&$a[1]==$b[1])throw new RuntimeException('Vértices consecutivos duplicados.',409);
                $area+=((float)$a[0]*(float)$b[1]-(float)$b[0]*(float)$a[1]);
                for($j=$i+2;$j<$n-1;$j++){
                    if($i===0&&$j===$n-2)continue;
                    if(self::intersection($a,$b,$ring[$j],$ring[$j+1]))throw new RuntimeException('Se detectó posible autointersección. Revisión GIS necesaria.',409);
                }
            }
            if(abs($area)<1e-12)throw new RuntimeException('Polígono sin superficie.',409);
            $conteo+=$n;
        }
        if($conteo>10000)throw new RuntimeException('Geometría demasiado compleja para revisión automática.',409);
        // No aceptar piezas superpuestas entre sí. Los casos complejos requieren GIS externo.
        for($p=0;$p<count($polys);$p++)for($q=$p+1;$q<count($polys);$q++){
            $a=$polys[$p][0];$b=$polys[$q][0];
            for($i=0;$i<count($a)-1;$i++)for($j=0;$j<count($b)-1;$j++){
                if(self::intersection($a[$i],$a[$i+1],$b[$j],$b[$j+1]))
                    throw new RuntimeException('Las piezas geométricas se tocan o intersectan. Revisión GIS especializada.',409);
            }
            if(self::dentro($a[0],$b)||self::dentro($b[0],$a))
                throw new RuntimeException('Una pieza geométrica contiene otra. Revisión GIS especializada.',409);
        }
        return ['piezas'=>count($polys),'vertices'=>$conteo];
    }
    public function resolver(int $id,string $decision,string $fundamento,int $usuario,bool $confirmado): array {
        if($id<1||$usuario<1||!in_array($decision,['APROBADA','RECHAZADA'],true)||mb_strlen(trim($fundamento))<10)
            throw new InvalidArgumentException('Solicitud, decisión y fundamento (mín. 10 caracteres) obligatorios.',400);
        if($decision==='APROBADA'&&!$confirmado)throw new InvalidArgumentException('Confirmá que revisaste la jurisdicción y su polígono.',400);
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare("SELECT s.*,j.nombre,j.tipo AS tipo_jurisdiccion,j.estado AS estado_juris,
                  ST_AsGeoJSON(s.geom_propuesta) AS geometria_json, ST_SRID(s.geom_propuesta) AS srid
                FROM geoterr.geo_jurisdicciones_solicitudes s
                JOIN geoterr.geo_jurisdicciones j ON j.id_jurisdiccion=s.id_jurisdiccion
                WHERE s.id_solicitud=? FOR UPDATE");$s->execute([$id]);$sol=$s->fetch(PDO::FETCH_ASSOC);
            if(!$sol)throw new RuntimeException('Solicitud no encontrada.',404);
            if($sol['estado']!=='PENDIENTE')throw new RuntimeException('La solicitud ya fue resuelta o no está pendiente.',409);
            if($sol['tipo']!=='CAMBIO_GEOMETRIA')throw new RuntimeException('Tipo de solicitud no soportado todavía por esta revisión.',409);
            $jur=(int)$sol['id_jurisdiccion'];
            $s=$this->db->prepare("SELECT id_geometria,version FROM geoterr.geo_jurisdicciones_geometrias WHERE id_jurisdiccion=? AND estado='APROBADO' ORDER BY version DESC FOR UPDATE");
            $s->execute([$jur]);$vigentes=$s->fetchAll(PDO::FETCH_ASSOC);
            if(count($vigentes)>1)throw new RuntimeException('Hay más de una geometría aprobada: bloquear y revisar.',409);
            $base=$vigentes?(int)$vigentes[0]['version']:0;
            if((int)$sol['version_base']!==$base)throw new RuntimeException('La versión base cambió. La solicitud debe revisarse nuevamente.',409);
            $geomID=null;$nuevaVersion=null;$detalleGIS=null;
            if($decision==='APROBADA'){
                if((int)$sol['srid']!==4326||!$sol['geometria_json'])throw new RuntimeException('La geometría no tiene SRID 4326.',409);
                $geom=json_decode((string)$sol['geometria_json'],true,512,JSON_THROW_ON_ERROR);
                $detalleGIS=self::validarGeometria($geom);
                // No superponer polígonos oficiales del MISMO nivel territorial.
                $q=$this->db->prepare("SELECT j.id_jurisdiccion,j.nombre,
                  ST_Area(ST_Intersection(g.geometria, s.geom_propuesta)) AS area_solape
                  FROM geoterr.geo_jurisdicciones_geometrias g
                  JOIN geoterr.geo_jurisdicciones j ON j.id_jurisdiccion=g.id_jurisdiccion
                  JOIN geoterr.geo_jurisdicciones_solicitudes s ON s.id_solicitud=?
                  WHERE g.estado='APROBADO' AND j.tipo=? AND j.id_jurisdiccion<>? AND j.activo=1
                  AND MBRIntersects(g.geometria,s.geom_propuesta)");
                $q->execute([$id,$sol['tipo_jurisdiccion'],$jur]);
                foreach($q->fetchAll(PDO::FETCH_ASSOC) as $otro){
                    if($otro['area_solape']===null||((float)$otro['area_solape'])>1e-12)
                        throw new RuntimeException('Solapamiento o comparación espacial indeterminada con jurisdicción ID '.$otro['id_jurisdiccion'].'. Revisión GIS requerida.',409);
                }
                if($vigentes){
                    $q=$this->db->prepare("UPDATE geoterr.geo_jurisdicciones_geometrias SET estado='HISTORICO' WHERE id_geometria=? AND estado='APROBADO'");
                    $q->execute([(int)$vigentes[0]['id_geometria']]);
                    if($q->rowCount()!==1)throw new RuntimeException('No se pudo historizar la versión anterior.',409);
                }
                $q=$this->db->prepare('SELECT COALESCE(MAX(version),0)+1 FROM geoterr.geo_jurisdicciones_geometrias WHERE id_jurisdiccion=?');
                $q->execute([$jur]);$nuevaVersion=(int)$q->fetchColumn();
                $q=$this->db->prepare("INSERT INTO geoterr.geo_jurisdicciones_geometrias
                    (id_jurisdiccion,version,geometria,origen,estado,observaciones,usuario_creacion,usuario_aprobacion,fecha_aprobacion)
                    SELECT id_jurisdiccion,?,geom_propuesta,'LEAFLET','APROBADO',?,usuario_solicitante,?,NOW()
                    FROM geoterr.geo_jurisdicciones_solicitudes WHERE id_solicitud=? AND geom_propuesta IS NOT NULL");
                $q->execute([$nuevaVersion,'Solicitud #'.$id.': '.mb_substr(trim($fundamento),0,200),$usuario,$id]);
                if($q->rowCount()!==1)throw new RuntimeException('No se creó la nueva geometría oficial.',409);
                $geomID=(int)$this->db->lastInsertId();
                $this->db->prepare("UPDATE geoterr.geo_jurisdicciones SET estado='APROBADO',fecha_actualizacion=NOW() WHERE id_jurisdiccion=?")->execute([$jur]);
                // Validar vínculo RRHH propuesto, si existe. No tocar RRHH ni vinculación DDP.
                $this->db->prepare("UPDATE geoterr.geo_jurisdicciones_rrhh SET estado='VALIDADO',fecha_desde=COALESCE(fecha_desde,NOW()),id_usuario=? WHERE id_jurisdiccion=? AND estado='PROPUESTO' AND clase='TITULAR'")->execute([$usuario,$jur]);
            }
            $cid=AuditoriaTerritorio::correlacion();
            $q=$this->db->prepare("UPDATE geoterr.geo_jurisdicciones_solicitudes SET estado=? WHERE id_solicitud=? AND estado='PENDIENTE'");
            $q->execute([$decision,$id]);if($q->rowCount()!==1)throw new RuntimeException('No se pudo resolver la solicitud.',409);
            $q=$this->db->prepare("INSERT INTO geoterr.geo_jurisdicciones_resoluciones(id_solicitud,decision,fundamento,usuario_revisor,correlacion_id) VALUES(?,?,?,?,?)");
            $q->execute([$id,$decision,trim($fundamento),$usuario,$cid]);
            $audit=new AuditoriaTerritorio($this->db);
            if($decision==='APROBADA'){
                $audit->registrarABM(['usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::GEOMETRIA,'tabla'=>'geoterr.geo_jurisdicciones_geometrias','registro'=>$geomID,'correlacion'=>$cid,
                    'antes'=>['jurisdiccion'=>$jur,'geometria_anterior'=>$vigentes[0]['id_geometria']??null,'version_anterior'=>$base],
                    'despues'=>['geometria_nueva'=>$geomID,'version_nueva'=>$nuevaVersion,'srid'=>4326,'validaciones'=>$detalleGIS],
                    'observacion'=>'Publicación geométrica jurisdicción #'.$jur]);
            }
            $audit->registrarABM(['usuario'=>$usuario,'tipo'=>$decision==='APROBADA'?AuditoriaTerritorio::APROBACION:AuditoriaTerritorio::RECHAZO,
                'tabla'=>'geoterr.geo_jurisdicciones_solicitudes','registro'=>$id,'correlacion'=>$cid,
                'antes'=>['estado'=>'PENDIENTE','version_base'=>$base,'jurisdiccion'=>$jur],
                'despues'=>['estado'=>$decision,'fundamento'=>trim($fundamento),'id_geometria'=>$geomID,'version'=>$nuevaVersion],
                'observacion'=>($decision==='APROBADA'?'Aprobación':'Rechazo').' solicitud jurisdicción #'.$id]);
            $this->db->commit();return ['id_solicitud'=>$id,'estado'=>$decision,'version'=>$nuevaVersion,'id_geometria'=>$geomID,'correlacion'=>$cid];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
