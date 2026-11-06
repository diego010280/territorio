<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

class Usuario{
    private $pdo;

    public function __construct() {
       $this->pdo = Conexion::getConexion('BARRIOS');
                
    }

    public function getAuthorization($pgname){
        
        $modulo = filter_var($_SESSION['moduleid'], FILTER_SANITIZE_NUMBER_INT);
	    $usuario = filter_var($_SESSION['usuario_id'], FILTER_SANITIZE_NUMBER_INT);


        $query="SELECT permisos.permiso_ejec, permisos.permiso_alta,
        permisos.permiso_baja, permisos.permiso_mod, perfiles.perfil_EsAdm,
        autorizacion.autorizacion_organismos_id AS Aut_idorg,
        autorizacion_izq_der.GEN_Organismos_Izquierdo AS Aut_izq,
        autorizacion_izq_der.GEN_Organismos_Derecho AS Aut_der,
        autorizacion_izq_der.GEN_Organismos_Descripcion AS Desc_depend,
        objetos.objeto_id, perfiles.perfil_id, perfiles.perfil_descrip,
        autorizacion_nivel
        FROM segusuario.autorizacion
        JOIN segusuario.perfiles ON perfiles.perfil_id = autorizacion.autorizacion_perfil_id
        JOIN segusuario.permisos ON permisos.permiso_perfil_id = perfiles.perfil_id
        JOIN segusuario.objetos ON objetos.objeto_id = permisos.permiso_objeto_id
        JOIN segusuario.autorizacion_izq_der ON autorizacion_izq_der.Organismo_id = autorizacion.autorizacion_organismos_id
        WHERE autorizacion.autorizacion_usuario_id = :usuario
          AND perfiles.perfil_activo = 1
          AND perfiles.perfil_modulo_id = :modulo_perfil
          AND objetos.objeto_link LIKE :pgname
          AND objetos.objeto_modulo_id = :modulo_objeto
         ORDER BY autorizacion_izq_der.GEN_Organismos_Izquierdo";
        
        	
	$stmt =  $this->pdo->prepare($query);
	$stmt->bindValue(':usuario', $usuario, PDO::PARAM_INT);
	$stmt->bindValue(':pgname', $pgname, PDO::PARAM_STR);
	$stmt->bindValue(':modulo_perfil', $modulo, PDO::PARAM_INT);
	$stmt->bindValue(':modulo_objeto', $modulo, PDO::PARAM_INT);

	$stmt->execute();
	$resultado = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $resultado;

    }

    public function datosusuario($usuario_id){
        $query ="SELECT * FROM segusuario.usuarios 
                left join rrhh.view_ult_funcion on rrhh.view_ult_funcion.RRHH_DatosPers_NroDoc=segusuario.usuarios.usuario_dni WHERE usuario_id= ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$usuario_id]);
       		
		return $stmt->fetch();

    }

    public function preferenciausuario(){
        $usuario = filter_var($_SESSION['usuario_id'], FILTER_SANITIZE_NUMBER_INT); 
        
        $query ="SELECT *  FROM obde.obde_preferencias
	                LEFT JOIN rrhh.gen_organismos ON gen_organismos.GEN_ORGANISMOS_Id = obde.obde_preferencias_idorg WHERE pref_usu_id = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$usuario]); 
        return $stmt->fetchAll(PDO::FETCH_ASSOC);            
    }

    public function autorizacionusuario(array $datausuario):array {
    
    try {
      
      $query ="SELECT autorizacion_usuario_id,autorizacion_perfil_id,autorizacion_nivel,
      autorizacion_organismos_id,perfil_id,perfil_descrip,perfil_activo FROM segusuario.autorizacion
      inner join segusuario.perfiles on segusuario.perfiles.perfil_id=segusuario.autorizacion.autorizacion_perfil_id
      where autorizacion_usuario_id=:usuario and perfil_activo=1 and segusuario.perfiles.perfil_modulo_id=:modulo";

      $stmt =  $this->pdo->prepare($query);
      $stmt->bindValue(':usuario',(int) $datausuario['usuario_id'], PDO::PARAM_INT);
      $stmt->bindValue(':modulo', (int) $datausuario['modulo'], PDO::PARAM_INT);

      $stmt->execute();
      $result=$stmt->fetchAll(PDO::FETCH_ASSOC);

       return [
               'success' => true,
               'data'    => $result ?: null,
               'error'   => null
                ];

    } catch (PDOException $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta autorizacionusuario falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
       return [
                       'success' => false,
                        'data'    => null,
                       'error'    => $e->getMessage()
                       
                ];
    }  catch (Exception $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta autorizacionusuario falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        return [
              'success' => false,
               'data'    => null,
              'error'    => $e->getMessage()
              
        ];
    }

      
    }

    public function datos_rrhh(array $datausuario):array {
    
  /*   $pdo = Conexion::getConexion('db_g'); */
    
    try {
      $query="SELECT RRHH_DatosPers_Legajo as 'valor' FROM usuarios
      inner join rrhh.view_datospers on rrhh.view_datospers.RRHH_DatosPers_NroDoc=segusuario.usuarios.usuario_dni
      WHERE usuario_id=:usuario and RRHH_DatosPers_activo=1";

      
      $stmt =  $this->pdo->prepare($query);
      $stmt->bindValue(':usuario',(int) $datausuario['usuario_id'], PDO::PARAM_INT);
      $stmt->execute();

      $result=$stmt->fetch(PDO::FETCH_ASSOC);

       return [
               
            'success'    => true,
            'encontrado' => ($result['valor'] ?? 0) > 0,
            'cantidad'   => (int)($result['valor'] ?? 0),
            'error'      => null
           ];

      
    } catch (PDOException $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta autorizacionusuario falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
       return [
            'success'    => false,
            'encontrado' => false,
            'cantidad'   => 0,
            'error'      => $e->getMessage()
                       
                ];
    }  catch (Exception $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta autorizacionusuario falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        return [
            'success'    => false,
            'encontrado' => false,
            'cantidad'   => 0,
            'error'      => $e->getMessage()
              
        ];
    }

    }
    

    public function buscarUsuarioPorLegajo(int $usuario_id):array {
    
  /*   $pdo = Conexion::getConexion('db_g'); */
    
    try {
      $query="SELECT rd.RRHH_TipoGrados_Desc,rd.RRHH_DatosPers_Legajo,rd.RRHH_ApeNom,RRHH_DatosPers_NroDoc FROM segusuario.usuarios as u
      inner join rrhh.view_datospers as rd on rd.RRHH_DatosPers_NroDoc=u.usuario_dni
      WHERE u.usuario_id=:usuario and rd.RRHH_DatosPers_activo=1;";

      
      $stmt =  $this->pdo->prepare($query);
      $stmt->bindValue(':usuario',(int) $usuario_id, PDO::PARAM_INT);
      $stmt->execute();

      $result=$stmt->fetch(PDO::FETCH_ASSOC);

       return [
               
            'success'    => true,
            'data'    => $result ?? [],
            'error'      => null
           ];

      
    } catch (PDOException $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta autorizacionusuario falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
       return [
            'success'    => false,
            'data'    => [],
            'error'      => $e->getMessage()
                       
                ];
    }  catch (Exception $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta autorizacionusuario falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        return [
            'success'    => false,
            'data'    => [],
            'error'      => $e->getMessage()
              
        ];
    }

    }


    public function func_diaria_hr(array $datausuario):array {
        
        //$pdo = Conexion::getConexion('db_e');   
    
    try {
         $query="SELECT * FROM rrhh_fdiaria_horario WHERE RRHH_fdiaria_horario_nrolegajo =:legajo AND RRHH_fdiaria_horario_actual = 1 ORDER BY RRHH_fdiaria_horario_fechaini DESC";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':legajo', (int)$datausuario['RRHH_DatosPers_Legajo'], PDO::PARAM_INT);
        $stmt->execute();
        $result=$stmt->fetchAll(PDO::FETCH_ASSOC);
	
       
        return [
            'success' => true,
            'data'    => $result,
            'cantidad'=> count($result),
            'error'   => null
        ];
        
        
         
    } catch (PDOException $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta autorizacionusuario falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
       return [
             'success'   => false,
             'data'      => [],
             'cantidad'  => 0,
             'error'     => $e->getMessage()
                       
                ];
    }  catch (Exception $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta autorizacionusuario falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        return [
            'success'   => false,
             'data'      => [],
             'cantidad'      => 0,
             'error'     => $e->getMessage()
              
        ];
    }
    
    }

    public function fdiaria_rrhh(array $datausuario):array {
        //$pdo = Conexion::getConexion('db_e');
    try {
        
        $query="SELECT * FROM rrhh_funcion_diaria
               INNER JOIN rrhh.rrhh_funciones_tipofunciones ON rrhh.rrhh_funciones_tipofunciones.rrhh_funciones_TipoFunciones_id = rrhh.rrhh_funcion_diaria.RRHH_funcion_diaria_funcionId
               LEFT JOIN rrhh.gen_organismos ON rrhh.gen_organismos.GEN_ORGANISMOS_Id = rrhh.rrhh_funcion_diaria.RRHH_funcion_diaria_Idorganismo

               WHERE RRHH_funcion_diaria_fechainifunc <=:RRHH_funcion_diaria_fechainifunc AND
                     (rrhh_funcion_diaria_fechafin >=:rrhh_funcion_diaria_fechafin OR rrhh_funcion_diaria_fechafin is null) AND RRHH_funcion_diaria_nrolegajo =:legajo ORDER BY RRHH_funcion_diaria_fechainifunc";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->bindValue(':RRHH_funcion_diaria_fechainifunc', (string)$datausuario['fecha_actual'], PDO::PARAM_STR);
        $stmt->bindValue(':rrhh_funcion_diaria_fechafin', (string)$datausuario['fecha_actual'], PDO::PARAM_STR);
        $stmt->bindValue(':legajo', (int)$datausuario['RRHH_DatosPers_Legajo'], PDO::PARAM_INT);
        $stmt->execute();

        $result=$stmt->fetchAll(PDO::FETCH_ASSOC);

       
        return [
            'success' => true,
            'data'    => $result,
            'cantidad'=> count($result),
            'error'   => null
        ];
    } catch (PDOException $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta fdiaria_rrhh falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
       return [
             'success'   => false,
             'data'      => [],
             'cantidad'  => 0,
             'error'     => $e->getMessage()
                       
                ];
    }  catch (Exception $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta fdiaria_rrhh falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        return [
             'success'   => false,
             'data'      => [],
             'cantidad'      => 0,
             'error'     => $e->getMessage()
              
        ];
    }

    
    }

    public function fdiaria_horario_rrhh(array $datausuario):array {
        //$pdo = Conexion::getConexion('db_e');
    try {
        
        $query="SELECT * FROM rrhh.rrhh_fdiaria_horario

                INNER JOIN rrhh.rrhh_horarios ON rrhh.rrhh_horarios.rrhh_horarios_id = rrhh.rrhh_fdiaria_horario.RRHH_fdiaria_horario_horarios_id
               	LEFT JOIN rrhh.rrhh_fdiaria_confirmacion ON rrhh.rrhh_fdiaria_confirmacion.rrhh_fdiaria_conf_OrgId =:rrhh_fdiaria_conf_OrgId AND
                rrhh.rrhh_fdiaria_confirmacion.rrhh_fdiaria_conf_horaId = rrhh.rrhh_fdiaria_horario.RRHH_fdiaria_horario_horarios_id AND rrhh.rrhh_fdiaria_confirmacion.rrhh_fdiaria_conf_fecha =:rrhh_fdiaria_conf_fecha
                 
                WHERE RRHH_fdiaria_horario_fechaini <=:RRHH_fdiaria_horario_fechaini AND RRHH_fdiaria_horario_fechafin >=:RRHH_fdiaria_horario_fechafin AND RRHH_fdiaria_horario_nrolegajo =:Legajo";
        
        $stmt =  $this->pdo->prepare($query);
        $stmt->bindValue(':rrhh_fdiaria_conf_OrgId', (int)$datausuario['RRHH_funcion_diaria_Idorganismo'], PDO::PARAM_INT);
        $stmt->bindValue(':rrhh_fdiaria_conf_fecha', (string)$datausuario['fecha_actual'], PDO::PARAM_STR);
        $stmt->bindValue(':RRHH_fdiaria_horario_fechaini', (string)$datausuario['fecha_actual'], PDO::PARAM_STR);
        $stmt->bindValue(':RRHH_fdiaria_horario_fechafin', (string)$datausuario['fecha_actual'], PDO::PARAM_STR);
        $stmt->bindValue(':Legajo', (int)$datausuario['RRHH_DatosPers_Legajo'], PDO::PARAM_INT);
        $stmt->execute();

        $result=$stmt->fetchAll(PDO::FETCH_ASSOC);

       
        return [
            'success' => true,
            'data'    => $result,
            'cantidad'=> count($result),
            'error'   => null
        ];

    } catch (PDOException $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta fdiaria_horario_rrhh falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
       return [
             'success'   => false,
             'data'      => [],
             'cantidad'  => 0,
             'error'     => $e->getMessage()
                       
                ];
    }  catch (Exception $e) {
         escribirErrorXML(
                "Excepción Inesperada - La ejecución de la consulta fdiaria_horario_rrhh falló",
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        return [
             'success'   => false,
             'data'      => [],
             'cantidad'      => 0,
             'error'     => $e->getMessage()
              
        ];
    }

    
    }


   
}

   

?>
