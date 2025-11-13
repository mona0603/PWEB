<?php
class dbconnection {
    static public function connect() {
        $server = "localhost";
        $user = "root";
        $pass = ""; //DNP3278r6n9! O NADA xd
        $db = "PCI";

        try {
            $conexion = new mysqli($server, $user, $pass, $db);
            if ($conexion->connect_errno) {
                $response = (object)array("status" => 500, "message" => $conexion->connect_error);
                echo json_encode($response);
                die("Error de conexión: " . $conexion->connect_error);
            }
        } catch (Exception $e) {
            $response = (object)array("status" => 500, "message" => "Error a conectarse a la base de datos.");
            echo json_encode($response);
            exit;
        }
        return $conexion;
    }

}
?>