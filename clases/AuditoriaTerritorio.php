<?php
declare(strict_types=1);
/**
 * Servicio compartido de auditoria para Territorio.
 * REUTILIZAR la misma conexion PDO (misma instancia) que la escritura de negocio.
 * ABM: ante error NO atrapar silenciosamente; debe abortar/rollback la transaccion.
 * Acciones de lectura: opcionalmente registrar con registrarAccionSegura().
 */
final class AuditoriaTerritorio {
    public const MODULO = 1239; // modulo institucional Territorio
    public const ALTA = 1;
    public const MODIFICACION = 2;
    public const BAJA_LOGICA = 3;
    public const APROBACION = 4;
    public const RECHAZO = 5;
    public const CONSOLIDACION = 6;
    public const CONSULTA = 7;
    public const BUSQUEDA = 8;
    public const EXPORTAR_EXCEL = 9;
    public const EXPORTAR_PDF = 10;
    public const IMPRIMIR = 11;
    public const CAMBIO_COLOR = 12;
    public const GEOMETRIA = 13;
    public const JURISDICCION = 14;

    private PDO $pdo;
    public function __construct(PDO $pdo) {$this->pdo=$pdo;}

    public static function correlacion(): string {
        $raw = bin2hex(random_bytes(16));
        return substr($raw,0,8).'-'.substr($raw,8,4).'-4'.substr($raw,13,3).'-'
            .dechex((hexdec($raw[16]) & 3) | 8).substr($raw,17,3).'-'.substr($raw,20,12);
    }

    private static function json(?array $data): ?string {
        if ($data === null) return null;
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function contexto(): array {
        // No resolver DNS inverso por solicitud; puede ralentizar la aplicacion.
        return [
            'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),
            'agente'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255),
            'host'=>null,
            'dispositivo'=>null,
        ];
    }

    /** $datos: usuario, tipo, tabla, registro, antes?, despues?, observacion?, correlacion?, persona?, modulo? */
    public function registrarABM(array $datos): int {
        $usuario=(int)($datos['usuario']??0);
        $tipo=(int)($datos['tipo']??0);
        $tabla=trim((string)($datos['tabla']??''));
        if ($usuario<=0 || $tipo<=0 || $tabla==='') throw new InvalidArgumentException('Auditoria ABM incompleta.');
        $contexto=self::contexto();
        $sql='INSERT INTO geoterr.geo_audit_abm (
            geo_audit_idtipacc,geo_audit_idmod,geo_audit_idusu,geo_audit_tabla_afectada,
            geo_audit_registro_id,geo_audit_correlacion_id,geo_audit_idpersona,
            geo_audit_datos_antes,geo_audit_datos_despues,geo_audit_observacion,
            geo_audit_ip,geo_audit_user_agent,geo_audit_host_cliente,geo_audit_dispositivo_id
          ) VALUES (:tipo,:modulo,:usuario,:tabla,:registro,:correlacion,:persona,
                    :antes,:despues,:observacion,:ip,:agente,:host,:dispositivo)';
        $s=$this->pdo->prepare($sql);
        $s->execute([
            ':tipo'=>$tipo, ':modulo'=>(int)($datos['modulo']??self::MODULO), ':usuario'=>$usuario,
            ':tabla'=>substr($tabla,0,150), ':registro'=>isset($datos['registro'])?substr((string)$datos['registro'],0,100):null,
            ':correlacion'=>$datos['correlacion']??null, ':persona'=>$datos['persona']??null,
            ':antes'=>self::json($datos['antes']??null), ':despues'=>self::json($datos['despues']??null),
            ':observacion'=>isset($datos['observacion'])?mb_substr((string)$datos['observacion'],0,255):null,
            ':ip'=>$contexto['ip'],':agente'=>$contexto['agente'],':host'=>$contexto['host'],':dispositivo'=>$contexto['dispositivo']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Acciones de lectura/exportacion: usuario, tipo, observacion?, parametros?, modulo? */
    public function registrarAccion(array $datos): int {
        $usuario=(int)($datos['usuario']??0);
        $tipo=(int)($datos['tipo']??0);
        if($usuario<=0 || $tipo<=0)throw new InvalidArgumentException('Auditoria de accion incompleta.');
        $contexto=self::contexto();
        $s=$this->pdo->prepare('INSERT INTO geoterr.geo_audit_accion (
          geo_audit_accion_idmod,geo_audit_accion_idusu,geo_audit_accion_idtipacc,
          geo_audit_accion_observ,geo_audit_accion_param,geo_audit_accion_ip,
          geo_audit_accion_user_agent,geo_audit_accion_host_cliente,geo_audit_accion_dispositivo_id
        ) VALUES (:modulo,:usuario,:tipo,:observacion,:parametros,:ip,:agente,:host,:dispositivo)');
        $s->execute([
            ':modulo'=>(int)($datos['modulo']??self::MODULO),':usuario'=>$usuario,':tipo'=>$tipo,
            ':observacion'=>isset($datos['observacion'])?mb_substr((string)$datos['observacion'],0,255):null,
            ':parametros'=>self::json($datos['parametros']??null),
            ':ip'=>$contexto['ip'],':agente'=>$contexto['agente'],':host'=>$contexto['host'],':dispositivo'=>$contexto['dispositivo']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Para lecturas solamente: falla de auditoria no bloquea la consulta. Nunca usar en ABM. */
    public function registrarAccionSegura(array $datos): void {
        try {$this->registrarAccion($datos);}
        catch(Throwable $e) {error_log('[TERRITORIO] Error al auditar accion: '.$e->getMessage());}
    }
}
