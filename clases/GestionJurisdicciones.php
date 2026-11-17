<?php
declare(strict_types=1);
/** Etapa 1: búsqueda RRHH, alta preliminar, propuesta de polígono.
 * La aprobación y publicación no están habilitadas en este servicio.
 */
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
              WHERE o.GEN_Organismos_Descripcion LIKE :term OR CAST(o.GEN_ORGANISMOS_Id AS CHAR)=:exact
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
    public function crearDesdeRRHH(int $idRRHH,int $usuario): array {
        if($idRRHH<1||$usuario<1)throw new InvalidArgumentException('ID RRHH o usuario inválido.',400);
        $aud=new AuditoriaTerritorio($this->db);
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT GEN_ORGANISMOS_Id,GEN_Organismos_Descripcion,GEN_Organismos_categ_Id,GEN_Organismos_Activo FROM rrhh.gen_organismos WHERE GEN_ORGANISMOS_Id=? LIMIT 1');
            $s->execute([$idRRHH]);$r=$s->fetch(PDO::FETCH_ASSOC);
            if(!$r)throw new RuntimeException('Organismo inexistente en RRHH.',404);
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
    private static function geojsonAWkt(array $g):string {
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
