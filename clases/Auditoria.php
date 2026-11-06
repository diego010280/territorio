<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../views/errores/errores.php';

class Auditoria
{
    public const LOGIN = 1;
    public const EJECUCION = 2;
    public const ALTA = 3;
    public const BAJA = 4;
    public const MODIFICACION = 5;
    public const CONSULTA = 6;
    public const DESCARGA = 7;
    public const EXPORTACION = 8;
    public const IMPRESION = 9;
    public const LOGOUT = 10;
    public const ACCESO_DENEGADO = 11;
    public const EXPORTACION_EXCEL = 12;
    public const EXPORTACION_PDF = 13;

    private PDO $db;

    public function __construct(?PDO $pdoSiad = null)
    {
        $this->db = $pdoSiad ?? Conexion::getConexion('BARRIOS');
    }

    public function registrarAuditoriaAccion(array $datos): array
    {
        try {
            $sql = "INSERT INTO siad_audit_accion (
                        siad_audit_accion_idmod,
                        siad_audit_accion_idusu,
                        siad_audit_accion_idtipacc,
                        siad_audit_accion_observ,
                        siad_audit_accion_param,
                        siad_audit_accion_ip,
                        siad_audit_accion_user_agent,
                        siad_audit_accion_host_cliente,
                        siad_audit_accion_dispositivo_id,
                        siad_audit_accion_fecha_evento
                    ) VALUES (
                        :idmod, :idusu, :idtipacc,
                        NULLIF(:observacion, ''),
                        NULLIF(:parametros, ''),
                        NULLIF(:ip, ''),
                        NULLIF(:user_agent, ''),
                        NULLIF(:host_cliente, ''),
                        NULLIF(:dispositivo_id, ''),
                        :fecha_evento
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':idmod' => (int) ($datos['siad_audit_accion_idmod'] ?? 0),
                ':idusu' => (int) ($datos['siad_audit_accion_idusu'] ?? 0),
                ':idtipacc' => (int) ($datos['siad_audit_accion_idtipacc'] ?? 0),
                ':observacion' => trim((string) ($datos['siad_audit_accion_observ'] ?? '')),
                ':parametros' => $this->convertirJson(
                    $datos['siad_audit_accion_param'] ?? null
                ),
                ':ip' => trim((string) ($datos['siad_audit_accion_ip'] ?? '')),
                ':user_agent' => trim((string) ($datos['siad_audit_accion_user_agent'] ?? '')),
                ':host_cliente' => trim((string) ($datos['siad_audit_accion_host_cliente'] ?? '')),
                ':dispositivo_id' => trim((string) ($datos['siad_audit_accion_dispositivo_id'] ?? '')),
                ':fecha_evento' => $this->fechaEvento(
                    $datos['siad_audit_accion_fecha_evento'] ?? null
                ),
            ]);

            return $this->resultadoExitoso();
        } catch (Throwable $e) {
            return $this->resultadoError('Registrar auditoría de acción', $e);
        }
    }

    public function registrarAuditoriaAbm(array $datos): array
    {
        try {
            $tipoAccion = (int) ($datos['siad_audit_idtipacc'] ?? 0);
            if (!in_array($tipoAccion, [self::ALTA, self::BAJA, self::MODIFICACION], true)) {
                throw new InvalidArgumentException(
                    'La auditoría ABM solo admite Alta, Baja o Modificación.'
                );
            }

            $tabla = trim((string) ($datos['siad_audit_tabla_afectada'] ?? ''));
            if ($tabla === '') {
                throw new InvalidArgumentException('Debe informar la tabla afectada.');
            }

            $personaId = (int) ($datos['siad_audit_idpersona'] ?? 0);

            $sql = "INSERT INTO siad_audit_abm (
                        siad_audit_idtipacc,
                        siad_audit_idmod,
                        siad_audit_idusu,
                        siad_audit_tabla_afectada,
                        siad_audit_registro_id,
                        siad_audit_correlacion_id,
                        siad_audit_idpersona,
                        siad_audit_datos_antes,
                        siad_audit_datos_despues,
                        siad_audit_observacion,
                        siad_audit_ip,
                        siad_audit_user_agent,
                        siad_audit_host_cliente,
                        siad_audit_dispositivo_id,
                        siad_audit_fecha_evento
                    ) VALUES (
                        :idtipacc, :idmod, :idusu, :tabla,
                        NULLIF(:registro_id, ''),
                        NULLIF(:correlacion_id, ''),
                        :persona_id,
                        NULLIF(:datos_antes, ''),
                        NULLIF(:datos_despues, ''),
                        NULLIF(:observacion, ''),
                        NULLIF(:ip, ''),
                        NULLIF(:user_agent, ''),
                        NULLIF(:host_cliente, ''),
                        NULLIF(:dispositivo_id, ''),
                        :fecha_evento
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':idtipacc', $tipoAccion, PDO::PARAM_INT);
            $stmt->bindValue(':idmod', (int) ($datos['siad_audit_idmod'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':idusu', (int) ($datos['siad_audit_idusu'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':tabla', $tabla, PDO::PARAM_STR);
            $stmt->bindValue(':registro_id', trim((string) ($datos['siad_audit_registro_id'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':correlacion_id', trim((string) ($datos['siad_audit_correlacion_id'] ?? '')), PDO::PARAM_STR);

            if ($personaId > 0) {
                $stmt->bindValue(':persona_id', $personaId, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':persona_id', null, PDO::PARAM_NULL);
            }

            $stmt->bindValue(':datos_antes', $this->convertirJson($datos['siad_audit_datos_antes'] ?? null), PDO::PARAM_STR);
            $stmt->bindValue(':datos_despues', $this->convertirJson($datos['siad_audit_datos_despues'] ?? null), PDO::PARAM_STR);
            $stmt->bindValue(':observacion', trim((string) ($datos['siad_audit_observacion'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':ip', trim((string) ($datos['siad_audit_ip'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', trim((string) ($datos['siad_audit_user_agent'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':host_cliente', trim((string) ($datos['siad_audit_host_cliente'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':dispositivo_id', trim((string) ($datos['siad_audit_dispositivo_id'] ?? '')), PDO::PARAM_STR);
            $stmt->bindValue(':fecha_evento', $this->fechaEvento($datos['siad_audit_fecha_evento'] ?? null), PDO::PARAM_STR);
            $stmt->execute();

            return $this->resultadoExitoso();
        } catch (Throwable $e) {
            return $this->resultadoError('Registrar auditoría ABM', $e);
        }
    }

    public function registrarAuditoriaError(array $datos): array
    {
        try {
            $sql = "INSERT INTO siad_audit_error (
                        siad_audit_error_idmod,
                        siad_audit_error_idusu,
                        siad_audit_error_idtiperr,
                        siad_audit_error_msje,
                        siad_audit_error_archiv,
                        siad_audit_error_linea,
                        siad_audit_error_codigo_err,
                        siad_audit_error_context,
                        siad_audit_error_ip,
                        siad_audit_error_user_agent,
                        siad_audit_error_host_cliente,
                        siad_audit_error_iddispositivo,
                        siad_audit_error_fecha_evento,
                        siad_audit_error_desctip_err
                    ) VALUES (
                        NULLIF(:idmod, 0), NULLIF(:idusu, 0), :idtiperr,
                        :mensaje, NULLIF(:archivo, ''), NULLIF(:linea, ''),
                        NULLIF(:codigo, ''), NULLIF(:contexto, ''),
                        NULLIF(:ip, ''), NULLIF(:user_agent, ''),
                        NULLIF(:host_cliente, ''), NULLIF(:dispositivo_id, ''),
                        :fecha_evento, NULLIF(:descripcion, '')
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':idmod' => (int) ($datos['siad_audit_error_idmod'] ?? 0),
                ':idusu' => (int) ($datos['siad_audit_error_idusu'] ?? 0),
                ':idtiperr' => (int) ($datos['siad_audit_error_idtiperr'] ?? 0),
                ':mensaje' => (string) ($datos['siad_audit_error_msje'] ?? ''),
                ':archivo' => (string) ($datos['siad_audit_error_archiv'] ?? ''),
                ':linea' => (string) ($datos['siad_audit_error_linea'] ?? ''),
                ':codigo' => (string) ($datos['siad_audit_error_codigo_err'] ?? ''),
                ':contexto' => $this->convertirJson($datos['siad_audit_error_context'] ?? null),
                ':ip' => (string) ($datos['siad_audit_error_ip'] ?? ''),
                ':user_agent' => (string) ($datos['siad_audit_error_user_agent'] ?? ''),
                ':host_cliente' => (string) ($datos['siad_audit_error_host_cliente'] ?? ''),
                ':dispositivo_id' => (string) ($datos['siad_audit_error_iddispositivo'] ?? ''),
                ':fecha_evento' => $this->fechaEvento($datos['siad_audit_error_fecha_evento'] ?? null),
                ':descripcion' => (string) ($datos['siad_audit_error_desctip_err'] ?? ''),
            ]);

            return $this->resultadoExitoso();
        } catch (Throwable $e) {
            return $this->resultadoError('Registrar auditoría de error', $e);
        }
    }

    private function convertirJson(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        if (is_string($valor)) {
            return $valor;
        }

        return json_encode(
            $valor,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private function fechaEvento(mixed $valor): string
    {
        $fecha = trim((string) ($valor ?? ''));
        return $fecha !== '' ? $fecha : date('Y-m-d H:i:s');
    }

    private function resultadoExitoso(): array
    {
        return [
            'success' => true,
            'error' => null,
            'insert_id' => $this->db->lastInsertId(),
        ];
    }

    private function resultadoError(string $titulo, Throwable $e): array
    {
        escribirErrorXML($titulo, $e->getMessage(), $e->getFile(), $e->getLine());

        return [
            'success' => false,
            'error' => $e->getMessage(),
            'insert_id' => null,
        ];
    }
}
