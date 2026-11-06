<?php

$hora_actual= date('Y-m-d H:i:s');
 //echo "hora actual: ".$hora_actual.'<br>';

$fechaconfirmacion= $row_turno['rrhh_fdiaria_conf_fecha'];
$hora_desde= $row_turno['rrhh_horarios_entrada'];
$fechahora_desde=$fechaconfirmacion.' '.$hora_desde;

//echo "hora_desde: ".$fechahora_desde.'<br>';

$valor=$fechahora_desde." + ".$horasumar." hours";
$hora_hasta= date('Y-m-d H:i:s',strtotime("$valor"));
 //echo "hora_hasta: ".$hora_hasta.'<br>';

//$autorizado = 1; //solo entorno de prueba

if ($hora_hasta>$hora_actual) {
   //echo "autorizado a ingresar".'<br>';
  $autorizado=1;
}else {
  // echo "autorizado a no ingresar".'<br>';
  $autorizado=0;
}




 ?>
