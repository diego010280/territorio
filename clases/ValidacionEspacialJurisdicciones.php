<?php
declare(strict_types=1);
/** ÚNICA regla espacial para prevalidación de Gestión, corrección y aprobación.
 * El área es grados² (NO metros cuadrados). La validez administrativa y ausencia de huecos se revisan aparte.
 */
final class ValidacionEspacialJurisdicciones {
    public const UMBRAL_SOLAPE = 1e-12;
    public static function evaluar(PDO $db,int $jur,array $geom):array {
        if($jur<1)throw new InvalidArgumentException('Jurisdicción inválida.',400);
        // Topología conservadora de anillos simples: misma rutina usada por Revisión.
        $estructura=RevisionJurisdicciones::validarGeometria($geom);
        $wkt=GestionJurisdicciones::geojsonAWkt($geom);
        $q=$db->prepare("SELECT tipo FROM geoterr.geo_jurisdicciones WHERE id_jurisdiccion=? AND activo=1");
        $q->execute([$jur]);$tipo=$q->fetchColumn();
        if(!$tipo)throw new RuntimeException('Jurisdicción inexistente o inactiva.',404);
        // Misma comparación para dibujo, revisión de pendiente y publicación: mismo nivel territorial, sin incluirse.
        $sql="SELECT j.id_jurisdiccion,j.nombre,
              ST_Intersects(g.geometria,ST_GeomFromText(?,4326)) AS intersecta,
              ST_Touches(g.geometria,ST_GeomFromText(?,4326)) AS contacto,
              ST_Area(ST_Intersection(g.geometria,ST_GeomFromText(?,4326))) AS area_solape
              FROM geoterr.geo_jurisdicciones_geometrias g
              JOIN geoterr.geo_jurisdicciones j ON j.id_jurisdiccion=g.id_jurisdiccion
              WHERE g.estado='APROBADO' AND j.activo=1 AND j.tipo=? AND j.id_jurisdiccion<>?
                AND MBRIntersects(g.geometria,ST_GeomFromText(?,4326))
              ORDER BY j.id_jurisdiccion LIMIT 202";
        $q=$db->prepare($sql);$q->execute([$wkt,$wkt,$wkt,$tipo,$jur,$wkt]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($rows)>200)throw new RuntimeException('Más de 200 vecinos en la comparación: requiere revisión GIS.',409);
        $conflictos=[];$contactos=[];$indeterminados=[];
        foreach($rows as $r){
            $info=['id_jurisdiccion'=>(int)$r['id_jurisdiccion'],'nombre'=>(string)$r['nombre']];
            if($r['intersecta']===null||$r['contacto']===null||$r['area_solape']===null){$indeterminados[]=$info;continue;}
            $area=(float)$r['area_solape'];
            if(!is_finite($area)||$area<0){$indeterminados[]=$info;continue;}
            if($area>self::UMBRAL_SOLAPE){$conflictos[]=$info+['area_grados2'=>$area];continue;}
            if((int)$r['contacto']===1){$contactos[]=$info;continue;}
            if((int)$r['intersecta']===1 && $area>0){$indeterminados[]=$info+['area_grados2'=>$area];continue;}
            if((int)$r['intersecta']===1 && $area===0.0){$contactos[]=$info;}
        }
        return ['conflictos'=>$conflictos,'contactos'=>$contactos,'indeterminados'=>$indeterminados,
            'estructura'=>$estructura,'umbral_grados2'=>self::UMBRAL_SOLAPE,
            'nota'=>'Diagnóstico de solape del mismo nivel territorial. No certifica ausencia de espacios ni corrige límites automáticamente.'];
    }
    public static function exigirSinConflictos(array $d):void {
        if(!empty($d['conflictos'])){
            $p=$d['conflictos'][0];throw new RuntimeException('La geometría se superpone con jurisdicción '.$p['id_jurisdiccion'].' ('.$p['nombre'].'). Corregí el límite antes de guardar/presentar.',409);
        }
        if(!empty($d['indeterminados'])){
            $p=$d['indeterminados'][0];throw new RuntimeException('Comparación espacial indeterminada con jurisdicción '.$p['id_jurisdiccion'].'. Revisión GIS requerida.',409);
        }
    }
}
