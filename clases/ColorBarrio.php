<?php
declare(strict_types=1);
require_once __DIR__.'/../config/database.php';
final class ColorBarrio {
    private PDO $db;
    public function __construct() { $this->db=Conexion::getConexion('BARRIOS'); }
    public function consultar(int $id): array {
        $s=$this->db->prepare("SELECT id_barrio,nombre,estado,activo,color_mapa FROM db_barrios.bar_barrios WHERE id_barrio=:id");
        $s->execute([':id'=>$id]); $b=$s->fetch(PDO::FETCH_ASSOC);
        if (!$b) throw new RuntimeException('Barrio inexistente',404);
        return $b;
    }
    public function guardar(int $id,string $color,int $usuario): array {
        $color=strtoupper(trim($color));
        if ($id<1 || $usuario<1 || !preg_match('/^#[0-9A-F]{6}$/D',$color))
            throw new InvalidArgumentException('Color hexadecimal inválido',400);
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT id_barrio,nombre,estado,activo,color_mapa FROM db_barrios.bar_barrios WHERE id_barrio=:id FOR UPDATE');
            $s->execute([':id'=>$id]);$b=$s->fetch(PDO::FETCH_ASSOC);
            if (!$b) throw new RuntimeException('Barrio inexistente',404);
            if ($b['estado']!=='APROBADO' || (int)$b['activo']!==1) throw new RuntimeException('Solo se puede asignar color a barrios aprobados y activos',409);
            $s=$this->db->prepare("SELECT COUNT(*) FROM db_barrios.bar_relaciones_barrios WHERE id_barrio_origen=:id AND estado='APROBADA'");
            $s->execute([':id'=>$id]);
            if ((int)$s->fetchColumn()>0) throw new RuntimeException('El barrio está subordinado a un gobernante',409);
            $anterior=$b['color_mapa'];
            if ($anterior!==$color) {
                $s=$this->db->prepare('UPDATE db_barrios.bar_barrios SET color_mapa=:color WHERE id_barrio=:id');
                $s->execute([':color'=>$color,':id'=>$id]);
                $s=$this->db->prepare('INSERT INTO geoterr.geo_auditoria (modulo,entidad,id_registro,accion,usuario_id,descripcion,datos_anteriores,datos_nuevos,ip,user_agent) VALUES (:m,:e,:id,:a,:u,:d,:prev,:next,:ip,:agent)');
                $s->execute([':m'=>'TERRITORIO',':e'=>'BARRIO_COLOR',':id'=>$id,':a'=>'MODIFICAR_COLOR_MAPA',':u'=>$usuario,
                    ':d'=>'Cambio de color cartográfico para barrio aprobado '.$b['nombre'],
                    ':prev'=>json_encode(['color_mapa'=>$anterior],JSON_THROW_ON_ERROR),
                    ':next'=>json_encode(['color_mapa'=>$color],JSON_THROW_ON_ERROR),
                    ':ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),
                    ':agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,250)]);
            }
            $this->db->commit();
            return ['ok'=>true,'id_barrio'=>$id,'color_mapa'=>$color,'modificado'=>$anterior!==$color];
        } catch (Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
