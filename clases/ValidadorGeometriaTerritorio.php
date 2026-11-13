<?php
declare(strict_types=1);
/**
 * Validador conservador SIN dependencias: solamente MULTIPOLYGON con UN polígono
 * y UN anillo exterior. Los agujeros/múltiples piezas requieren validación GIS externa.
 * Comprueba estructura y autointersecciones de segmentos; NO sustituye una evaluación
 * técnica/administrativa de linderos oficiales.
 */
final class ValidadorGeometriaTerritorio {
    private const EPS = 1.0e-12;
    private static function cruz(array $a,array $b,array $c):float {
        return ($b[0]-$a[0])*($c[1]-$a[1])-($b[1]-$a[1])*($c[0]-$a[0]);
    }
    private static function mismo(array $a,array $b):bool {
        return abs($a[0]-$b[0])<self::EPS && abs($a[1]-$b[1])<self::EPS;
    }
    private static function enSegmento(array $a,array $b,array $p):bool {
        return abs(self::cruz($a,$b,$p))<self::EPS &&
            $p[0]>=min($a[0],$b[0])-self::EPS && $p[0]<=max($a[0],$b[0])+self::EPS &&
            $p[1]>=min($a[1],$b[1])-self::EPS && $p[1]<=max($a[1],$b[1])+self::EPS;
    }
    private static function cruzan(array $a,array $b,array $c,array $d):bool {
        $x=self::cruz($a,$b,$c);$y=self::cruz($a,$b,$d);
        $z=self::cruz($c,$d,$a);$w=self::cruz($c,$d,$b);
        if ((($x>self::EPS&&$y<-self::EPS)||($x<-self::EPS&&$y>self::EPS)) &&
            (($z>self::EPS&&$w<-self::EPS)||($z<-self::EPS&&$w>self::EPS)))return true;
        return (abs($x)<self::EPS&&self::enSegmento($a,$b,$c)) ||
            (abs($y)<self::EPS&&self::enSegmento($a,$b,$d)) ||
            (abs($z)<self::EPS&&self::enSegmento($c,$d,$a)) ||
            (abs($w)<self::EPS&&self::enSegmento($c,$d,$b));
    }
    public static function validar(string $geojson):array {
        $g=json_decode($geojson,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($g)||($g['type']??'')!=='MultiPolygon'||!isset($g['coordinates'])||!is_array($g['coordinates']))
            throw new RuntimeException('Se exige una geometría GeoJSON MULTIPOLYGON.',409);
        if(count($g['coordinates'])!==1||!is_array($g['coordinates'][0])||count($g['coordinates'][0])!==1)
            throw new RuntimeException('Validación GIS limitada: multipartes o polígonos con huecos requieren revisión externa.',409);
        $ring=$g['coordinates'][0][0];
        if(!is_array($ring)||count($ring)<4||count($ring)>10000)
            throw new RuntimeException('El anillo exterior debe tener entre 4 y 10000 posiciones.',409);
        $p=[];
        foreach($ring as $v){
            if(!is_array($v)||count($v)!==2||!is_numeric($v[0])||!is_numeric($v[1]))
                throw new RuntimeException('Coordenadas geométricas inválidas.',409);
            $lon=(float)$v[0];$lat=(float)$v[1];
            if(!is_finite($lon)||!is_finite($lat)||abs($lon)>180||abs($lat)>90)
                throw new RuntimeException('Coordenadas fuera de rango WGS84.',409);
            $p[]=[$lon,$lat];
        }
        $n=count($p)-1;
        if(!self::mismo($p[0],$p[$n]))throw new RuntimeException('El anillo no está cerrado.',409);
        $areaDoble=0.0;
        for($i=0;$i<$n;$i++){
            $j=$i+1;
            if(self::mismo($p[$i],$p[$j]))throw new RuntimeException('El polígono contiene aristas de longitud nula.',409);
            $areaDoble+=$p[$i][0]*$p[$j][1]-$p[$j][0]*$p[$i][1];
        }
        if(abs($areaDoble)<1.0e-12)throw new RuntimeException('La geometría propuesta tiene superficie nula.',409);
        for($i=0;$i<$n;$i++)for($j=$i+1;$j<$n;$j++){
            // Aristas consecutivas comparten un vértice legítimo.
            if($j===$i+1 || ($i===0&&$j===$n-1))continue;
            if(self::cruzan($p[$i],$p[$i+1],$p[$j],$p[$j+1]))
                throw new RuntimeException('El polígono tiene segmentos que se cruzan o se tocan indebidamente.',409);
        }
        return ['vertices'=>$n,'anillos'=>1,'partes'=>1,'superficie_grados2'=>abs($areaDoble)/2];
    }
}
