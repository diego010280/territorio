<?php
    $dato_usuario= new Usuario();
    $consulta_legajo=[];
    $row_perfil=$dato_usuario->autorizacionusuario(
      ['usuario_id'=> $_SESSION['usuario_id'],
       'modulo'=> $_SESSION['moduleid']
      ]);

    $libre=0;
    $horasumar="";
    $hora_hasta="";

    if ($row_perfil['success'] && !empty($row_perfil['data'])) {
        
        foreach ($row_perfil['data'] as $fila) {

            if ($fila['autorizacion_nivel']==1 or $fila['autorizacion_nivel']==2 ) {
                  $libre=1;
              break;
            }

        } //cierra foreach

        if ($libre==1) {
          $autorizado=1;
        }else {
        
           $fecha_actual=date('Y-m-d');
           $legajo= $dato_usuario->datos_rrhh(['usuario_id'=> $_SESSION['usuario_id']]);

           
            if ($legajo['success'] && $legajo['cantidad'] > 0) {
                /****** chequeo con que fecha trabaja**************************************/
                //$row_usu_legajo=$legajo['data'][0];

                $fecha_turno= $dato_usuario->func_diaria_hr(['RRHH_DatosPers_Legajo'=> $legajo['encontrado'][0]['valor']]);

                if ($fecha_turno['success'] && $fecha_turno['cantidad'] > 0) {
                    $row_turnohorario=$fecha_turno['data'][0];

                    if ((!empty($row_turnohorario['RRHH_fdiaria_horario_fechafin'])) and ($row_turnohorario['RRHH_fdiaria_horario_fechafin']<=$fecha_actual)) {
                        // consultas con la fecha actual...

                        switch ($row_turnohorario['RRHH_fdiaria_horario_horarios_id']) {
                            case 3:
                                $fecha_actual=$row_turnohorario['RRHH_fdiaria_horario_fechafin'];
                                break;
                            
                            case 7:
                                $fecha_actual=$row_turnohorario['RRHH_fdiaria_horario_fechafin'];
                                break;  
                        
                            case 8:
                                $fecha_actual=$row_turnohorario['RRHH_fdiaria_horario_fechafin'];
                                break;
                                
                            case 10:
                                $fecha_actual=$row_turnohorario['RRHH_fdiaria_horario_fechafin'];
                                break;
                            
                            case 11:
                                $fecha_actual=$row_turnohorario['RRHH_fdiaria_horario_fechafin'];
                                break;
                        }//cierra switch
                    }
                } else {
                    $error="Error: La consulta de turno horario fallo o la cantidad de busqueda dio cero porque el personal policial fuera de actividad o en su defecto no es policia";
                    /* cargar en auditoria y no dejar entrar */
                }
                
            } else {
                $error="Error: La consulta de datos personales fallo o la cantidad de busqueda dio cero porque el personal policial fuera de actividad o en su defecto no es policia";
                /* cargar en auditoria y no dejar entrar */
            }

           /* ver la funcion diario */

        $consulta_fdiaria= $dato_usuario->fdiaria_rrhh(
          ['RRHH_DatosPers_Legajo'=> $legajo['cantidad'],
          'fecha_actual'=> $fecha_actual
          ]);

            if ($consulta_fdiaria['success'] && $consulta_fdiaria['cantidad'] > 0) {
                $row_fdiaria=$consulta_fdiaria['data'][0];

                if ($row_fdiaria['rrhh_funciones_TipoFunciones_id']==22) {
                    //pregunta si tiene cargado SIN FUNCIONES no ingresa
                    $autorizado=0;
                } else {
                    // si tiene funciones seguimos verificando si tiene confirmacion de turno...
                    $dep= $row_fdiaria['RRHH_funcion_diaria_Idorganismo'];
                }

                //**************en funcion diaria horaria esta lo q se registro del personal en la fecha y el turno en que esta********////

                $turno= $dato_usuario->fdiaria_horario_rrhh(
                    ['RRHH_DatosPers_Legajo'=> $legajo['encontrado'][0]['valor'],
                    'fecha_actual'=> $fecha_actual,
                    'RRHH_funcion_diaria_Idorganismo'=> $dep
                    ]);
                
                if ($turno['success'] && $turno['cantidad'] > 0) {
                    $row_turno=$turno['data'][0]; 

                    switch ($row_turno['RRHH_fdiaria_horario_horarios_id']) {
                                      case 1:
                                            // Tercio mañana: 06:00:00
                                            $horasumar=9;
                                            include ('../validar_hora_sesionusu.php');
                                        break;

                                      case 2:
                                              // Tercio Tarde: 13:00:00
                                              $horasumar=9;
                                              include ('../validar_hora_sesionusu.php');
                                          break;

                                      case 3:
                                            // Tercio Noche: 21:00:00
                                            $horasumar=9;
                                          include ('../validar_hora_sesionusu.php');
                                        break;

                                      case 4:
                                              // Turno Mañana: 07:00:00
                                              $horasumar=9;
                                              include ('../validar_hora_sesionusu.php');
                                          break;

                                      case 5:
                                                // Tercio Tarde: 15:00:00
                                                $horasumar=9;
                                                include ('../validar_hora_sesionusu.php');
                                            break;

                                      case 6:
                                              // Turno dia 12 hs: 07:00:00
                                              $horasumar=13;
                                            include ('../validar_hora_sesionusu.php');
                                          break;

                                      case 7:
                                        // Turno noche 12 hs: 19:00:00
                                          $horasumar=13;
                                          include ('../validar_hora_sesionusu.php');
                                        break;

                                      case 8:
                                        // Turno 24 hs: 06:00:00
                                          $horasumar=24;
                                          include ('../validar_hora_sesionusu.php');
                                        break;

                                      case 9:
                                        // Mañana y Tarde: 08:00:00.
                                          $horasumar=15;
                                          include ('../validar_hora_sesionusu.php');
                                          break;

                                      case 10:
                                        // Mañana y Noche: 09:00:00
                                        $horasumar=20;
                                        include ('../validar_hora_sesionusu.php');
                                        break;

                                      case 11:
                                        // Tarde y Noche: 14:00:00
                                        $horasumar=20;
                                          include ('../validar_hora_sesionusu.php');
                                        break;

                                      case 90:
                                        // Franco serv. 1er día
                                        $autorizado=0;
                                        break;

                                      case 91:
                                          // Franco serv. 2do día
                                          $autorizado=0;
                                          break;

                                      case 92:
                                            // Franquicia
                                            $autorizado=0;
                                            break;

                                      case 93:
                                              // Franco de Servicio
                                              $autorizado=0;
                                              break;

                                      case 100:
                                        // Sin Turno
                                        $autorizado=0;
                                        break;

                                      case 101:
                                        // Lic. Médicas
                                        $autorizado=0;
                                        break;

                                      case 102:
                                        // Lic. varias
                                        $autorizado=0;
                                        break;

                                      case 110:
                                          //Sin Turno
                                          $autorizado=0;
                                          break;


                                    } // cierra switch

                }else {
                     // si no tiene registro el turno es porque tiene algo y no esta confirmado por lo tanto debe consultar su mesa entrada o no cumple por carpeta etc...
                     //echo "no paso turno" ;
                     $autorizado=0;
                }    
                
                
            } else {
                //ver q hacer el error de consulta.....
                $error="Error: La ejecución de busqueda funcion diaria para sacar dependencia del usuario fallo o la cantidad de busqueda dio cero porque el personal policial fuera de actividad o en su defecto no es policia";
                /* cargar en auditoria y no dejar entrar */
            }

        }
      
    }
    
 



 ?>
