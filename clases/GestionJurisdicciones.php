<?php
declare(strict_types=1);
/** Etapa 1: búsqueda RRHH, alta preliminar, propuesta de polígono.
 * La aprobación y publicación no están habilitadas en este servicio.
 */
require_once __DIR__.'/ValidacionEspacialJurisdicciones.php';
final class GestionJurisdicciones {
    public function __construct(private PDO $db) {}
    public function buscarRRHH(string $texto): array {
        $texto=trim($texto);
        if(mb_strlen($texto)<2) throw new InvalidArgumentException('Ingrese al menos dos caracteres.',400);
        $texto=mb_substr($texto,0,90);
        $sql="SELECT o.GEN_ORGANISMOS_Id AS id_rrhh,
                     o.GEN_Organismos_Descripcion AS nombre,
                     o.GEN_Organismos_categ_Id AS categoria,
                     o.GEN_Organismos_Activo AS activo,
                     j.id_jurisdiccion, j.estado AS estado_territorio
              FROM rrhh.gen_organismos o
              LEFT JOIN geoterr.geo_jurisdicciones_rrhh v
                ON v.id_organismo_rrhh=o.GEN_ORGANISMOS_Id AND v.estado IN('PROPUESTO','VALIDADO')
              LEFT JOIN geoterr.geo_jurisdicciones j ON j.id_jurisdiccion=v.id_jurisdiccion AND j.activo=1
              WHERE o.GEN_Organismos_categ_Id IN (52,53,54,55,56)
                AND (o.GEN_Organismos_Descripcion LIKE :term OR CAST(o.GEN_ORGANISMOS_Id AS CHAR)=:exact)
              ORDER BY o.GEN_Organismos_Descripcion LIMIT 35";
        $s=$this->db->prepare($sql);
        $s->execute([':term'=>'%'.$texto.'%',':exact'=>$texto]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listar(string $texto): array {
        $sql="SELECT j.id_jurisdiccion,j.tipo,j.nombre,j.codigo_externo,j.numero_ddp,j.estado,j.color_mapa,
                     v.id_organismo_rrhh, (SELECT MAX(g.version) FROM geoterr.geo_jurisdicciones_geometrias g
                        WHERE g.id_jurisdiccion=j.id_jurisdiccion) AS ultima_version,
                     (SELECT COUNT(*) FROM geoterr.geo_jurisdicciones_geometrias g
                        WHERE g.id_jurisdiccion=j.id_jurisdiccion AND g.estado='APROBADO') AS oficiales
                FROM geoterr.geo_jurisdicciones j LEFT JOIN geoterr.geo_jurisdicciones_rrhh v
                  ON v.id_jurisdiccion=j.id_jurisdiccion AND v.estado IN('PROPUESTO','VALIDADO')
               WHERE (:filtro='' OR j.nombre LIKE :nombre OR CAST(v.id_organismo_rrhh AS CHAR)=:codigo)
               ORDER BY j.tipo,j.nombre LIMIT 100";
        $s=$this->db->prepare($sql);
        $texto=mb_substr(trim($texto),0,90);
        $s->execute([':filtro'=>$texto,':nombre'=>'%'.$texto.'%',':codigo'=>$texto]);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    }


    /** Grilla paginada. Solo lectura y con filtros vinculados a RRHH, sin modificar RRHH. */
    public function listarPaginado(array $f): array {
        $pagina=max(1,min(100000,(int)($f['pagina']??1)));
        $tam=max(10,min(100,(int)($f['tam']??25)));
        $q=mb_substr(trim((string)($f['q']??'')),0,90);
        $tipo=(string)($f['tipo']??'');
        $estado=(string)($f['estado']??'');
        $geo=(string)($f['geometria']??'');
        $ddp=(int)($f['ddp']??0);
        if(!in_array($tipo,['','DDP','DEPENDENCIA'],true))throw new InvalidArgumentException('Tipo inválido.',400);
        if(!in_array($estado,['','PRELIMINAR','APROBADO','INACTIVO'],true))throw new InvalidArgumentException('Estado inválido.',400);
        if(!in_array($geo,['','APROBADA','SIN_APROBAR','BORRADOR'],true))throw new InvalidArgumentException('Filtro de geometría inválido.',400);
        if($ddp<0||$ddp>14)throw new InvalidArgumentException('DDP inválido.',400);
        $conds=['j.activo=1'];$args=[];
        if($q!==''){
            $conds[]="(j.nombre LIKE :nom OR j.codigo_externo LIKE :cod OR CAST(j.id_jurisdiccion AS CHAR)=:idexact OR EXISTS (
                 SELECT 1 FROM geoterr.geo_jurisdicciones_rrhh vr
                 WHERE vr.id_jurisdiccion=j.id_jurisdiccion AND vr.estado IN ('PROPUESTO','VALIDADO')
                   AND CAST(vr.id_organismo_rrhh AS CHAR)=:rrhh))";
            $args[':nom']='%'.$q.'%';$args[':cod']='%'.$q.'%';$args[':idexact']=$q;$args[':rrhh']=$q;
        }
        if($tipo!==''){$conds[]='j.tipo=:tipo';$args[':tipo']=$tipo;}
        if($estado!==''){$conds[]='j.estado=:estado';$args[':estado']=$estado;}
        if($ddp>0){$conds[]='j.numero_ddp=:ddp';$args[':ddp']=$ddp;}
        $geoExiste="EXISTS (SELECT 1 FROM geoterr.geo_jurisdicciones_geometrias ge WHERE ge.id_jurisdiccion=j.id_jurisdiccion AND ge.estado='APROBADO')";
        if($geo==='APROBADA')$conds[]=$geoExiste;
        elseif($geo==='SIN_APROBAR')$conds[]='NOT '.$geoExiste;
        elseif($geo==='BORRADOR')$conds[]="EXISTS (SELECT 1 FROM geoterr.geo_jurisdicciones_geometrias gb WHERE gb.id_jurisdiccion=j.id_jurisdiccion AND gb.estado='BORRADOR')";
        $where=implode(' AND ',$conds);
        $count=$this->db->prepare('SELECT COUNT(*) FROM geoterr.geo_jurisdicciones j WHERE '.$where);
        $count->execute($args);$total=(int)$count->fetchColumn();
        $offset=($pagina-1)*$tam;
        $sql="SELECT j.id_jurisdiccion,j.nombre,j.tipo,j.numero_ddp,j.estado,j.color_mapa,j.codigo_externo,
               (SELECT MIN(v.id_organismo_rrhh) FROM geoterr.geo_jurisdicciones_rrhh v
                WHERE v.id_jurisdiccion=j.id_jurisdiccion AND v.estado IN ('VALIDADO','PROPUESTO')) AS id_organismo_rrhh,
               (SELECT MAX(gg.version) FROM geoterr.geo_jurisdicciones_geometrias gg
                WHERE gg.id_jurisdiccion=j.id_jurisdiccion AND gg.estado='APROBADO') AS version_oficial,
               (SELECT COUNT(*) FROM geoterr.geo_jurisdicciones_geometrias gb
                WHERE gb.id_jurisdiccion=j.id_jurisdiccion AND gb.estado='BORRADOR') AS borradores,
               (SELECT COUNT(*) FROM geoterr.geo_jurisdicciones_solicitudes s
                WHERE s.id_jurisdiccion=j.id_jurisdiccion AND s.estado='PENDIENTE') AS pendientes
             FROM geoterr.geo_jurisdicciones j WHERE ".$where.
             " ORDER BY j.tipo,j.nombre,j.id_jurisdiccion LIMIT ".$tam." OFFSET ".$offset;
        $stmt=$this->db->prepare($sql);$stmt->execute($args);
        return ['data'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'pagina'=>$pagina,'tam'=>$tam];
    }

    /** Datos en SOLO LECTURA. BORRADOR es orientativo y nunca es oficial. */
    public function capas(string $estado='APROBADO', ?int $id=null, int $limite=200): array {
        if(!in_array($estado,['APROBADO','BORRADOR'],true)) throw new InvalidArgumentException('Estado no permitido.',400);
        $limite=max(1,min(250,$limite));
        $sql="SELECT j.id_jurisdiccion,j.tipo,j.nombre,j.numero_ddp,j.codigo_externo,j.estado AS estado_juris,
                 j.color_mapa,g.id_geometria,g.version,g.estado AS estado_geometria,
                 ST_AsGeoJSON(g.geometria) AS geojson, ST_SRID(g.geometria) AS srid,
                 (SELECT MIN(v.id_organismo_rrhh) FROM geoterr.geo_jurisdicciones_rrhh v
                  WHERE v.id_jurisdiccion=j.id_jurisdiccion AND v.estado IN('PROPUESTO','VALIDADO')) AS id_rrhh
              FROM geoterr.geo_jurisdicciones_geometrias g
              JOIN geoterr.geo_jurisdicciones j ON j.id_jurisdiccion=g.id_jurisdiccion
              WHERE g.estado=:estado AND j.activo=1 ";
        $args=[':estado'=>$estado];
        if($estado==='APROBADO') $sql.=" AND j.estado='APROBADO' ";
        if($id!==null) {$sql.=" AND j.id_jurisdiccion=:id ";$args[':id']=$id;}
        $sql.=" AND g.version=(SELECT MAX(g2.version) FROM geoterr.geo_jurisdicciones_geometrias g2
                  WHERE g2.id_jurisdiccion=g.id_jurisdiccion AND g2.estado=g.estado)
                 ORDER BY j.tipo,j.nombre LIMIT ".$limite;
        $stmt=$this->db->prepare($sql);$stmt->execute($args);
        $features=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $geom=json_decode((string)$r['geojson'],true);
            if(!is_array($geom)||!isset($geom['type']))continue;
            $features[]=['type'=>'Feature','geometry'=>$geom,'properties'=>[
                'id_jurisdiccion'=>(int)$r['id_jurisdiccion'],'id_geometria'=>(int)$r['id_geometria'],
                'nombre'=>$r['nombre'],'tipo'=>$r['tipo'],'numero_ddp'=>$r['numero_ddp'],
                'id_rrhh'=>$r['id_rrhh'],'codigo_externo'=>$r['codigo_externo'],
                'estado'=>$r['estado_geometria'],'version'=>(int)$r['version'],
                'color_mapa'=>$r['color_mapa'],'srid'=>(int)$r['srid']]];
        }
        return ['type'=>'FeatureCollection','features'=>$features];
    }

    public function crearDesdeRRHH(int $idRRHH,int $usuario): array {
        if($idRRHH<1||$usuario<1)throw new InvalidArgumentException('ID RRHH o usuario inválido.',400);
        $aud=new AuditoriaTerritorio($this->db);
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT GEN_ORGANISMOS_Id,GEN_Organismos_Descripcion,GEN_Organismos_categ_Id,GEN_Organismos_Activo FROM rrhh.gen_organismos WHERE GEN_ORGANISMOS_Id=? LIMIT 1');
            $s->execute([$idRRHH]);$r=$s->fetch(PDO::FETCH_ASSOC);
            if(!$r)throw new RuntimeException('Organismo inexistente en RRHH.',404);
            if(!in_array((int)$r['GEN_Organismos_categ_Id'],[52,53,54,55,56],true)) throw new RuntimeException('Solo se permiten categorías RRHH 52, 53, 54, 55 y 56.',403);
            $s=$this->db->prepare("SELECT j.id_jurisdiccion FROM geoterr.geo_jurisdicciones_rrhh v JOIN geoterr.geo_jurisdicciones j ON j.id_jurisdiccion=v.id_jurisdiccion WHERE v.id_organismo_rrhh=? AND v.estado IN('PROPUESTO','VALIDADO') AND j.activo=1 LIMIT 1 FOR UPDATE");
            $s->execute([$idRRHH]);$exist=$s->fetchColumn();
            if($exist) throw new RuntimeException('El organismo ya está vinculado con una jurisdicción activa ID '.$exist,409);
            $codigo='RRHH-'.$idRRHH;
            $s=$this->db->prepare("INSERT INTO geoterr.geo_jurisdicciones(tipo,codigo_externo,nombre,estado,usuario_creacion) VALUES('DEPENDENCIA',?,?,'PRELIMINAR',?)");
            $s->execute([$codigo,$r['GEN_Organismos_Descripcion'],$usuario]);
            $id=(int)$this->db->lastInsertId();
            $s=$this->db->prepare("INSERT INTO geoterr.geo_jurisdicciones_rrhh(id_jurisdiccion,id_organismo_rrhh,denominacion_capturada,categoria_capturada,clase,estado,id_usuario) VALUES(?,?,?,?,'TITULAR','PROPUESTO',?)");
            $s->execute([$id,$idRRHH,$r['GEN_Organismos_Descripcion'],$r['GEN_Organismos_categ_Id'],$usuario]);
            $aud->registrarABM(['usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::ALTA,'tabla'=>'geoterr.geo_jurisdicciones','registro'=>$id,
              'despues'=>['id_rrhh'=>$idRRHH,'nombre'=>$r['GEN_Organismos_Descripcion'],'estado'=>'PRELIMINAR'],
              'observacion'=>'Alta preliminar de jurisdicción desde RRHH']);
            $this->db->commit();return ['id_jurisdiccion'=>$id,'estado'=>'PRELIMINAR'];
        } catch(Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    /** GeoJSON MultiPolygon o Polygon; valida formato y límites básicos. NO aprueba. */
    public function proponerGeometria(int $id,array $geom,string $motivo,int $usuario): array {
        if($id<1||$usuario<1||mb_strlen(trim($motivo))<10)throw new InvalidArgumentException('Jurisdicción, usuario y motivo (mínimo 10 caracteres) son obligatorios.',400);
        $wkt=self::geojsonAWkt($geom);
        $diagnostico=ValidacionEspacialJurisdicciones::evaluar($this->db,$id,$geom);
        ValidacionEspacialJurisdicciones::exigirSinConflictos($diagnostico);
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT id_jurisdiccion,estado FROM geoterr.geo_jurisdicciones WHERE id_jurisdiccion=? AND activo=1 FOR UPDATE');$s->execute([$id]);
            $jur=$s->fetch(PDO::FETCH_ASSOC);if(!$jur)throw new RuntimeException('Jurisdicción inexistente.',404);
            $s=$this->db->prepare("SELECT COUNT(*) FROM geoterr.geo_jurisdicciones_solicitudes WHERE id_jurisdiccion=? AND estado='PENDIENTE'");$s->execute([$id]);
            if((int)$s->fetchColumn()>0)throw new RuntimeException('Existe una solicitud pendiente para esta jurisdicción.',409);
            $s=$this->db->prepare("SELECT COALESCE(MAX(version),0) FROM geoterr.geo_jurisdicciones_geometrias WHERE id_jurisdiccion=? AND estado='APROBADO'");$s->execute([$id]);$base=(int)$s->fetchColumn();
            // Geometría sólo se conserva como propuesta hasta revisión GIS.
            $s=$this->db->prepare("INSERT INTO geoterr.geo_jurisdicciones_solicitudes(id_jurisdiccion,tipo,estado,version_base,geom_propuesta,motivo,usuario_solicitante) VALUES(?,'CAMBIO_GEOMETRIA','PENDIENTE',?,ST_GeomFromText(?,4326),?,?)");
            $s->execute([$id,$base,$wkt,trim($motivo),$usuario]);$sol=(int)$this->db->lastInsertId();
            (new AuditoriaTerritorio($this->db))->registrarABM([
              'usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::ALTA,'tabla'=>'geoterr.geo_jurisdicciones_solicitudes','registro'=>$sol,
              'despues'=>['jurisdiccion'=>$id,'version_base'=>$base,'tipo'=>'CAMBIO_GEOMETRIA','estado'=>'PENDIENTE','hash_wkt'=>hash('sha256',$wkt)],
              'observacion'=>'Propuesta geométrica pendiente de aprobación',
            ]);
            $this->db->commit();return ['id_solicitud'=>$sol,'estado'=>'PENDIENTE','version_base'=>$base];
        } catch(Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    /** Mismo validador espacial de Gestion y Revision. */
    public function comprobarSolapamiento(int $id, array $geometry): array {
        return ValidacionEspacialJurisdicciones::evaluar($this->db,$id,$geometry);
    }

    /** Obtener solo la propuesta PENDIENTE del territorio seleccionado; no consultar datos de otros sin autorización. */
    public function solicitudPendiente(int $id): array {
        if($id<1)throw new InvalidArgumentException('Jurisdicción inválida.',400);
        $q=$this->db->prepare("SELECT id_solicitud,id_jurisdiccion,estado,version_base,revision_actual,motivo,usuario_solicitante, ST_AsGeoJSON(geom_propuesta,12) AS geojson, SHA2(ST_AsBinary(geom_propuesta),256) AS huella FROM geoterr.geo_jurisdicciones_solicitudes WHERE id_jurisdiccion=? AND estado='PENDIENTE' ORDER BY id_solicitud DESC LIMIT 2");
        $q->execute([$id]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)>1)throw new RuntimeException('Hay más de una solicitud pendiente para la misma jurisdicción. Intervención administrativa requerida.',409);
        if(!$rows)return ['solicitud'=>null];
        $v=$rows[0];$v['geometria']=$v['geojson']?json_decode((string)$v['geojson'],true,512,JSON_THROW_ON_ERROR):null;unset($v['geojson']);
        return ['solicitud'=>$v];
    }

    /** Corrección versionada: misma solicitud, historia íntegra de ambas geometrías, ABM y control de concurrencia. */
    public function corregirSolicitud(int $idSol,int $revisionEsperada,int $jur,array $geometria,string $motivo,int $usuario):array {
        if($idSol<1||$revisionEsperada<1||$jur<1||$usuario<1||mb_strlen(trim($motivo))<10||mb_strlen(trim($motivo))>1000)
            throw new InvalidArgumentException('Solicitud, jurisdicción, versión y fundamento (10-1000 caracteres) son obligatorios.',400);
        $wkt=self::geojsonAWkt($geometria);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare("SELECT s.id_solicitud,s.id_jurisdiccion,s.revision_actual,s.estado,s.version_base,s.motivo,s.usuario_solicitante, SHA2(ST_AsBinary(s.geom_propuesta),256) AS hash_anterior FROM geoterr.geo_jurisdicciones_solicitudes s WHERE s.id_solicitud=? FOR UPDATE");
            $q->execute([$idSol]);$s=$q->fetch(PDO::FETCH_ASSOC);
            if(!$s || (int)$s['id_jurisdiccion']!==$jur)throw new RuntimeException('La solicitud no pertenece a la jurisdicción seleccionada.',404);
            if($s['estado']!=='PENDIENTE')throw new RuntimeException('La solicitud ya no está PENDIENTE.',409);
            if((int)$s['revision_actual']!==$revisionEsperada)throw new RuntimeException('Otra operación actualizó la propuesta. Volvé a cargarla antes de modificarla.',409);
            $q=$this->db->prepare("SELECT COALESCE(MAX(version),0) FROM geoterr.geo_jurisdicciones_geometrias WHERE id_jurisdiccion=? AND estado='APROBADO'");$q->execute([$jur]);
            if((int)$q->fetchColumn()!==(int)$s['version_base'])throw new RuntimeException('La geometría oficial cambió desde la presentación. Requiere nueva revisión.',409);
            // Misma validación de estructura y solape que usa la aprobación. No se publica nada.
            $diagnostico=ValidacionEspacialJurisdicciones::evaluar($this->db,$jur,$geometria);
            ValidacionEspacialJurisdicciones::exigirSinConflictos($diagnostico);
            $hashNuevo=hash('sha256',$wkt);
            $q=$this->db->prepare("SELECT SHA2(ST_AsBinary(ST_GeomFromText(?,4326)),256)");$q->execute([$wkt]);
            if(hash_equals((string)$s['hash_anterior'],(string)$q->fetchColumn()) && trim($motivo)===trim((string)$s['motivo']))
                throw new RuntimeException('No hay cambios en la propuesta ni en su fundamento.',409);
            $nueva=$revisionEsperada+1;$cid=AuditoriaTerritorio::correlacion();
            $q=$this->db->prepare("INSERT INTO geoterr.geo_jurisdicciones_solicitudes_revisiones (id_solicitud,revision_numero,geometria_antes,geometria_despues,motivo_antes,motivo_despues,usuario_revision,correlacion_id,hash_antes,hash_despues) SELECT id_solicitud,?,geom_propuesta,ST_GeomFromText(?,4326),motivo,?, ?,?, SHA2(ST_AsBinary(geom_propuesta),256),SHA2(ST_AsBinary(ST_GeomFromText(?,4326)),256) FROM geoterr.geo_jurisdicciones_solicitudes WHERE id_solicitud=? AND estado='PENDIENTE'");
            $q->execute([$nueva,$wkt,trim($motivo),$usuario,$cid,$wkt,$idSol]);
            if($q->rowCount()!==1)throw new RuntimeException('No pudo almacenarse el historial de la corrección.',409);
            $q=$this->db->prepare("UPDATE geoterr.geo_jurisdicciones_solicitudes SET geom_propuesta=ST_GeomFromText(?,4326),motivo=?,revision_actual=? WHERE id_solicitud=? AND estado='PENDIENTE' AND revision_actual=?");
            $q->execute([$wkt,trim($motivo),$nueva,$idSol,$revisionEsperada]);
            if($q->rowCount()!==1)throw new RuntimeException('Conflicto de revisión al guardar.',409);
            (new AuditoriaTerritorio($this->db))->registrarABM(['usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::MODIFICACION,'tabla'=>'geoterr.geo_jurisdicciones_solicitudes','registro'=>$idSol,'correlacion'=>$cid,
                'antes'=>['revision'=>$revisionEsperada,'hash'=>$s['hash_anterior'],'motivo'=>$s['motivo'],'jurisdiccion'=>$jur],
                'despues'=>['revision'=>$nueva,'hash'=>$hashNuevo,'motivo'=>trim($motivo),'jurisdiccion'=>$jur,'diagnostico'=>$diagnostico],
                'observacion'=>'Corrección de geometría de solicitud pendiente #'.$idSol]);
            $this->db->commit();return ['id_solicitud'=>$idSol,'estado'=>'PENDIENTE','revision'=>$nueva,'correlacion'=>$cid,'diagnostico'=>$diagnostico];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public static function geojsonAWkt(array $g):string {
        $type=(string)($g['type']??'');$polys=$type==='Polygon'?[$g['coordinates']??[]]:($type==='MultiPolygon'?($g['coordinates']??[]):null);
        if(!is_array($polys)||!$polys||count($polys)>25)throw new InvalidArgumentException('Se requiere Polygon/MultiPolygon con hasta 25 piezas.',400);
        $out=[];$total=0;
        foreach($polys as $poly){
            if(!is_array($poly)||!$poly||count($poly)>30)throw new InvalidArgumentException('Anillos inválidos.',400);
            $rings=[];
            foreach($poly as $ring){
                if(!is_array($ring)||count($ring)<4||count($ring)>3000)throw new InvalidArgumentException('Cada anillo debe tener de 4 a 3000 puntos.',400);
                $pairs=[];$first=null;$last=null;
                foreach($ring as $coord){
                    if(!is_array($coord)||count($coord)<2||!is_numeric($coord[0])||!is_numeric($coord[1]))throw new InvalidArgumentException('Coordenadas inválidas.',400);
                    $x=(float)$coord[0];$y=(float)$coord[1];
                    if(!is_finite($x)||!is_finite($y)||abs($x)>180||abs($y)>90)throw new InvalidArgumentException('Coordenadas fuera de rango.',400);
                    $str=sprintf('%.9F %.9F',$x,$y);$pairs[]=$str;$last=$str;if($first===null)$first=$str;
                    if(++$total>10000)throw new InvalidArgumentException('Demasiados puntos.',400);
                }
                if($first!==$last)throw new InvalidArgumentException('Anillo sin cierre.',400);
                $rings[]='('.implode(',',$pairs).')';
            }
            $out[]='('.implode(',',$rings).')';
        }
        return 'MULTIPOLYGON('.implode(',',$out).')';
    }
}
