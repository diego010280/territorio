<?php
declare(strict_types=1);
/** Cambio de color de jurisdicción aprobada, auditable y sin crear versión geométrica. */
final class ColorJurisdiccion {
    public function __construct(private PDO $db) {}
    public function consultar(int $id): array {
        if($id<1)throw new InvalidArgumentException('ID de jurisdicción inválido.',400);
        $q=$this->db->prepare("SELECT j.id_jurisdiccion,j.nombre,j.color_mapa,j.estado FROM geoterr.geo_jurisdicciones j WHERE j.id_jurisdiccion=? AND j.activo=1 LIMIT 1");
        $q->execute([$id]);$j=$q->fetch(PDO::FETCH_ASSOC);
        if(!$j)throw new RuntimeException('Jurisdicción no encontrada.',404);
        if($j['estado']!=='APROBADO')throw new RuntimeException('El color individual se asigna a jurisdicciones aprobadas.',409);
        return ['id_jurisdiccion'=>(int)$j['id_jurisdiccion'],'nombre'=>$j['nombre'],'color_mapa'=>$j['color_mapa']?:'#287C67'];
    }
    public function guardar(int $id,string $color,string $motivo,int $usuario): array {
        $color=strtoupper(trim($color));
        $motivo=trim($motivo);
        $longitud=mb_strlen($motivo,'UTF-8');
        if($longitud<10 || $longitud>255)throw new InvalidArgumentException('Ingresá un motivo de entre 10 y 255 caracteres.',400);
        if($id<1||$usuario<1)throw new InvalidArgumentException('Datos de usuario o jurisdicción inválidos.',400);
        if(!preg_match('/^#[0-9A-F]{6}$/D',$color))throw new InvalidArgumentException('Color hexadecimal inválido.',400);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id_jurisdiccion,nombre,estado,activo,color_mapa FROM geoterr.geo_jurisdicciones WHERE id_jurisdiccion=? FOR UPDATE');
            $q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
            if(!$r)throw new RuntimeException('Jurisdicción no encontrada.',404);
            if((int)$r['activo']!==1 || $r['estado']!=='APROBADO')throw new RuntimeException('Solo puede cambiarse el color de una jurisdicción aprobada activa.',409);
            $antes=$r['color_mapa']!==null?strtoupper((string)$r['color_mapa']):null;
            if($antes!==$color){
                $u=$this->db->prepare('UPDATE geoterr.geo_jurisdicciones SET color_mapa=?,fecha_actualizacion=NOW() WHERE id_jurisdiccion=?');
                $u->execute([$color,$id]);
                (new AuditoriaTerritorio($this->db))->registrarABM([
                    'usuario'=>$usuario,'tipo'=>AuditoriaTerritorio::CAMBIO_COLOR,
                    'tabla'=>'geoterr.geo_jurisdicciones','registro'=>$id,
                    'antes'=>['color_mapa'=>$antes],'despues'=>['color_mapa'=>$color,'motivo'=>$motivo],
                    'correlacion'=>AuditoriaTerritorio::correlacion(),
                    'observacion'=>mb_substr('Cambio de color cartográfico: '.$motivo,0,255,'UTF-8')
                ]);
            }
            $this->db->commit();
            return ['ok'=>true,'cambiado'=>$antes!==$color,'id_jurisdiccion'=>$id,'color_mapa'=>$color];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
