<?php //HOLA XITU, ME CAES MAL PORQUE ERAS UNA ANTIGUA MALA INFLUENCIA, PERO TE RESPETO COMO DESARROLLADOR Y COMO PERSONA. SIGUE ASÍ, QUE ESTÁS HACIENDO UN GRAN TRABAJO EN TU PROYECTO!


//UBIQUESE NUEVA SUCIA PUERCA
namespace Controllers;

use phpseclib3\Net\SFTP;
use Exception;
use Model\ActiveRecord;
use MVC\Router;
use Model\Mdep;
use Mpdf\Mpdf;

class MdepController extends ActiveRecord
{

    public static function renderizarPagina(Router $router)
    {
        $router->render('mdep/index', []);
    }

    public static function renderizarUbicaciones(Router $router)
    {
        $router->render('ubicaciones/index', []);
    }

    public static function guardarAPI()
    {
        header('Content-Type: application/json');

        $_POST['dep_desc_lg'] = trim(htmlspecialchars($_POST['dep_desc_lg']));
        $_POST['dep_desc_md'] = trim(htmlspecialchars($_POST['dep_desc_md']));
        $_POST['dep_desc_ct'] = trim(htmlspecialchars($_POST['dep_desc_ct']));
        $_POST['dep_clase'] = trim(htmlspecialchars($_POST['dep_clase']));
        $_POST['dep_latitud'] = trim(htmlspecialchars($_POST['dep_latitud'] ?? ''));
        $_POST['dep_longitud'] = trim(htmlspecialchars($_POST['dep_longitud'] ?? ''));
        $_POST['dep_ruta_logo'] = '';

        $_POST['dep_precio'] = '1';
        $_POST['dep_ejto'] = 'N';

        // VALIDACIONES
        if (empty($_POST['dep_desc_lg']) || strlen($_POST['dep_desc_lg']) < 10) {
            http_response_code(400);
            echo json_encode(['codigo' => 0, 'mensaje' => 'La descripción larga debe tener más de 10 caracteres']);
            exit;
        }

        if (empty($_POST['dep_desc_md']) || strlen($_POST['dep_desc_md']) < 5) {
            http_response_code(400);
            echo json_encode(['codigo' => 0, 'mensaje' => 'La descripción mediana debe tener más de 5 caracteres']);
            exit;
        }

        if (empty($_POST['dep_desc_ct']) || strlen($_POST['dep_desc_ct']) < 3) {
            http_response_code(400);
            echo json_encode(['codigo' => 0, 'mensaje' => 'La descripción corta debe tener más de 3 caracteres']);
            exit;
        }

        if (empty($_POST['dep_clase'])) {
            http_response_code(400);
            echo json_encode(['codigo' => 0, 'mensaje' => 'Clase es requerida']);
            exit;
        }

        try {

            // Manejar imagen
            if (isset($_FILES['dep_imagen']) && $_FILES['dep_imagen']['error'] === UPLOAD_ERR_OK) {
                $rutaImagen = self::subirImagen($_FILES['dep_imagen']);
                if ($rutaImagen) {
                    $_POST['dep_ruta_logo'] = $rutaImagen;
                } else {
                    error_log('SFTP no disponible, dependencia creada sin imagen');
                }
            }

            unset($_POST['dep_llave']);

            error_log('=== DATOS PARA INSERTAR ===');
            error_log(print_r($_POST, true));

            $mdep = new Mdep($_POST);
            $resultado = $mdep->crear();

            if ($resultado['resultado'] == 1) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'Dependencia creada correctamente' .
                        (empty($_POST['dep_ruta_logo']) && isset($_FILES['dep_imagen']) ?
                            ' (imagen no pudo ser subida - servidor no disponible)' : ''),
                    'id' => $resultado['id'] ?? null
                ]);
            } else {
                $mensaje = 'Error al crear dependencia';
                if (isset($resultado['error'])) {
                    if (strpos($resultado['error'], 'ID generado ya existe') !== false) {
                        $mensaje = 'Error de concurrencia, intente nuevamente';
                    } elseif (strpos($resultado['error'], 'Unique constraint') !== false) {
                        $mensaje = 'Datos duplicados. Verifique que la información no exista previamente';
                    } else {
                        $mensaje = $resultado['error'];
                    }
                }

                http_response_code(400);
                echo json_encode(['codigo' => 0, 'mensaje' => $mensaje]);
            }
        } catch (Exception $e) {
            error_log('Exception en guardarAPI: ' . $e->getMessage());

            $mensaje = 'Error interno del servidor';
            if (strpos($e->getMessage(), 'Unique constraint') !== false) {
                $mensaje = 'Datos duplicados. Verifique que la información no exista previamente';
            } elseif (strpos($e->getMessage(), 'ID generado ya existe') !== false) {
                $mensaje = 'Error de concurrencia, intente nuevamente';
            }

            http_response_code(500);
            echo json_encode(['codigo' => 0, 'mensaje' => $mensaje]);
        }
    }

    public static function buscarAPI()
    {
        header('Content-Type: application/json');

        try {
            $sql = "SELECT * FROM informix.mdep ORDER BY dep_llave DESC";
            $data = self::fetchArray($sql);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Dependencias obtenidas correctamente',
                'data' => $data
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al obtener dependencias'
            ]);
        }
    }

    public static function modificarAPI()
    {
        header('Content-Type: application/json');

        $id = $_POST['dep_llave'];

        $_POST['dep_desc_lg'] = trim(htmlspecialchars($_POST['dep_desc_lg']));
        $_POST['dep_desc_md'] = trim(htmlspecialchars($_POST['dep_desc_md']));
        $_POST['dep_desc_ct'] = trim(htmlspecialchars($_POST['dep_desc_ct']));
        $_POST['dep_clase'] = trim(htmlspecialchars($_POST['dep_clase']));
        $_POST['dep_latitud'] = trim(htmlspecialchars($_POST['dep_latitud'] ?? ''));
        $_POST['dep_longitud'] = trim(htmlspecialchars($_POST['dep_longitud'] ?? ''));

        $_POST['dep_precio'] = '1';
        $_POST['dep_ejto'] = 'N';

        try {
            // Consulta directa para obtener los datos
            $sql = "SELECT * FROM informix.mdep WHERE dep_llave = ?";
            $stmt = self::$db->prepare($sql);
            $stmt->execute([$id]);
            $dependenciaData = $stmt->fetch();

            if (!$dependenciaData) {
                http_response_code(400);
                echo json_encode(['codigo' => 0, 'mensaje' => 'Dependencia no existe']);
                return;
            }

            // Crear objeto Mdep con los datos existentes
            $dependencia = new Mdep($dependenciaData);

            // IMPORTANTE: Asegurarse que dep_llave esté asignado
            $dependencia->dep_llave = $id; // ← ESTA LÍNEA ES CRUCIAL

            // Manejar imagen
            if (isset($_FILES['dep_imagen']) && $_FILES['dep_imagen']['error'] === UPLOAD_ERR_OK) {
                $rutaImagen = self::subirImagen($_FILES['dep_imagen']);
                if ($rutaImagen) {
                    $_POST['dep_ruta_logo'] = $rutaImagen;
                } else {
                    $_POST['dep_ruta_logo'] = $dependencia->dep_ruta_logo;
                }
            } else {
                $_POST['dep_ruta_logo'] = $dependencia->dep_ruta_logo;
            }

            // Actualizar propiedades
            $dependencia->dep_desc_lg = $_POST['dep_desc_lg'];
            $dependencia->dep_desc_md = $_POST['dep_desc_md'];
            $dependencia->dep_desc_ct = $_POST['dep_desc_ct'];
            $dependencia->dep_clase = $_POST['dep_clase'];
            $dependencia->dep_latitud = $_POST['dep_latitud'] ?: null;
            $dependencia->dep_longitud = $_POST['dep_longitud'] ?: null;
            $dependencia->dep_ruta_logo = $_POST['dep_ruta_logo'];

            $resultado = $dependencia->actualizar();

            if ($resultado['resultado'] > 0) {
                http_response_code(200);
                echo json_encode([
                    'codigo' => 1,
                    'mensaje' => 'Dependencia modificada correctamente'
                ]);
            } else {
                throw new Exception('No se pudo actualizar la dependencia');
            }
        } catch (Exception $e) {
            error_log('Error en modificarAPI: ' . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al modificar dependencia: ' . $e->getMessage()
            ]);
        }
    }

    public static function deshabilitarAPI()
    {
        header('Content-Type: application/json');

        $id = $_POST['dep_llave'];
        $justificacion = trim(htmlspecialchars($_POST['justificacion']));

        if (strlen($justificacion) < 10) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La justificación debe tener al menos 10 caracteres'
            ]);
            exit;
        }

        try {
            /** @var Mdep $dependencia */
            $dependencia = Mdep::find($id);
            if (!$dependencia) {
                http_response_code(400);
                echo json_encode(['codigo' => 0, 'mensaje' => 'Dependencia no existe']);
                return;
            }

            $rutaPDF = self::generarPDF('DESHABILITACION', $dependencia->dep_desc_lg, $justificacion);


            Mdep::DeshabilitarDependencia($id);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Dependencia deshabilitada correctamente',
                'pdf_generado' => $rutaPDF
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al deshabilitar'
            ]);
        }
    }

    public static function habilitarAPI()
    {
        header('Content-Type: application/json');

        $id = $_POST['dep_llave'];
        $justificacion = trim(htmlspecialchars($_POST['justificacion']));

        if (strlen($justificacion) < 10) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'La justificación debe tener al menos 10 caracteres'
            ]);
            exit;
        }

        try {
            /** @var Mdep $dependencia */
            $dependencia = Mdep::find($id);
            if (!$dependencia) {
                http_response_code(400);
                echo json_encode(['codigo' => 0, 'mensaje' => 'Dependencia no existe']);
                return;
            }

            $rutaPDF = self::generarPDF('HABILITACION', $dependencia->dep_desc_lg, $justificacion);


            Mdep::HabilitarDependencia($id);

            http_response_code(200);
            echo json_encode([
                'codigo' => 1,
                'mensaje' => 'Dependencia habilitada correctamente',
                'pdf_generado' => $rutaPDF
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error al habilitar'
            ]);
        }
    }

    private static function subirImagen($archivo)
    {
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($archivo['type'], $tiposPermitidos)) {
            return false;
        }

        try {
            $sftpServidor = $_ENV['FILE_SERVER'] ?? '127.0.0.1';
            $sftpUsuario = $_ENV['FILE_USER'] ?? 'ftpuser';
            $sftpPassword = $_ENV['FILE_PASSWORD'] ?? 'ftppassword';
            $rutaRemota = '/upload/PruebaMDEP/';

            $sftp = new SFTP($sftpServidor);
            if (!$sftp->login($sftpUsuario, $sftpPassword)) {
                error_log('Error SFTP: No conexión a ' . $sftpServidor);
                return false;
            }

            $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
            $nombreArchivo = 'img_' . time() . '.' . $extension;

            $resultado = $sftp->put($rutaRemota . $nombreArchivo, $archivo['tmp_name'], SFTP::SOURCE_LOCAL_FILE);
            $sftp->disconnect();

            return $resultado ? $rutaRemota . $nombreArchivo : false;
        } catch (Exception $e) {
            error_log('SFTP Exception: ' . $e->getMessage());
            return false;
        }
    }
    private static function generarPDF($accion, $dependencia, $justificacion)
    {
        $mpdf = new Mpdf(['format' => 'A4']);

        $html = '
        <h1>Justificación de ' . $accion . '</h1>
        <p><strong>Dependencia:</strong> ' . $dependencia . '</p>
        <p><strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '</p>
        <p><strong>Justificación:</strong></p>
        <p>' . nl2br($justificacion) . '</p>
        ';

        $mpdf->WriteHTML($html);

        $nombrePDF = strtolower($accion) . '_' . time() . '.pdf';
        $rutaTemporal = sys_get_temp_dir() . '/' . $nombrePDF;
        $mpdf->Output($rutaTemporal, 'F');


        $sftpServidor = $_ENV['FILE_SERVER'] ?? '127.0.0.1';
        $sftpUsuario = $_ENV['FILE_USER'] ?? 'ftpuser';
        $sftpPassword = $_ENV['FILE_PASSWORD'] ?? 'ftppassword';
        $rutaRemotaPDF = '/upload/PruebaMDEP/JUSTIFICACIONES/';

        $sftp = new SFTP($sftpServidor);
        if ($sftp->login($sftpUsuario, $sftpPassword)) {
            $sftp->put($rutaRemotaPDF . $nombrePDF, $rutaTemporal, SFTP::SOURCE_LOCAL_FILE);
            $sftp->disconnect();
        }

        unlink($rutaTemporal);
        return $rutaRemotaPDF . $nombrePDF;
    }
    public static function obtenerPDFAPI()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'ID de dependencia requerido'
            ]);
            return;
        }

        try {

            $dependencia = Mdep::find($id);
            if (!$dependencia) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Dependencia no encontrada'
                ]);
                return;
            }

            $sftpServidor = $_ENV['FILE_SERVER'] ?? '127.0.0.1';
            $sftpUsuario = $_ENV['FILE_USER'] ?? 'ftpuser';
            $sftpPassword = $_ENV['FILE_PASSWORD'] ?? 'ftppassword';
            $rutaRemotaPDF = '/upload/PruebaMDEP/JUSTIFICACIONES/';

            $sftp = new SFTP($sftpServidor);
            if (!$sftp->login($sftpUsuario, $sftpPassword)) {
                http_response_code(500);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error de conexión al servidor de archivos'
                ]);
                return;
            }


            $archivos = $sftp->nlist($rutaRemotaPDF);
            $pdfEncontrado = null;


            foreach ($archivos as $archivo) {
                if (strpos($archivo, (string)$id) !== false && strpos($archivo, '.pdf') !== false) {
                    $pdfEncontrado = $rutaRemotaPDF . $archivo;
                    break;
                }
            }

            if (!$pdfEncontrado) {

                foreach ($archivos as $archivo) {
                    if ((strpos($archivo, 'habilitacion_') !== false || strpos($archivo, 'deshabilitacion_') !== false)
                        && strpos($archivo, '.pdf') !== false
                    ) {
                        $pdfEncontrado = $rutaRemotaPDF . $archivo;
                        break;
                    }
                }
            }

            if (!$pdfEncontrado) {
                http_response_code(404);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'No se encontró PDF de justificación para esta dependencia'
                ]);
                $sftp->disconnect();
                return;
            }

            $contenidoPDF = $sftp->get($pdfEncontrado);
            $sftp->disconnect();

            if ($contenidoPDF === false) {
                http_response_code(500);
                echo json_encode([
                    'codigo' => 0,
                    'mensaje' => 'Error al descargar el archivo PDF'
                ]);
                return;
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="justificacion_dependencia_' . $id . '.pdf"');
            header('Content-Length: ' . strlen($contenidoPDF));

            echo $contenidoPDF;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'codigo' => 0,
                'mensaje' => 'Error interno: ' . $e->getMessage()
            ]);
        }
    }
}
