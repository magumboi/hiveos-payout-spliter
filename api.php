<?php
/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/
require 'vendor/autoload.php';
use League\Csv\Reader;
use League\Csv\Statement;

header('Content-Type: application/json; charset=utf-8');

if (isset($_POST['file']) && isset($_POST['income']) && isset($_POST['payday']) && isset($_POST['workedDays'])) {
    main($_POST['income'],$_POST['payday'],$_POST['workedDays'],$_POST['isOwner']);
    /* //The resource that we want to download.
     $fileUrl = $_POST['file'];

     $filesaveTo = 'stats.csv';
 
     $fp = fopen($filesaveTo, 'w+');

     if($fp === false){
         throw new Exception('Could not open: ' . $filesaveTo);
     }

     $ch = curl_init($fileUrl);
     curl_setopt($ch, CURLOPT_FILE, $fp);
     curl_setopt($ch, CURLOPT_TIMEOUT, 20);
     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
     curl_exec($ch);

     if(curl_errno($ch)){
         throw new Exception(curl_error($ch));
     }

     $getstatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
     curl_close($ch);
     fclose($fp);
     if($getstatusCode == 200){
         //echo 'Downloaded!';
     } else{
         echo "Status Code: " . $getstatusCode;
     }*/
}

function main($income,$payday,$workedDays,$isOwner){

    //$payday = date("d/m/Y");

    $contractDateEnd = date('Y-m-d', strtotime(DateTime::createFromFormat('d/m/Y',$payday)->modify('-1 day')->format('Y-m-d')));
    $contractDateBegin = date('Y-m-d', strtotime(DateTime::createFromFormat('d/m/Y',$payday)->modify('-'.$workedDays.' day')->format('Y-m-d')));

    $resultado = array();
    $prom = array();
    $adead = array();
    $sumavg = 0;
    $sum = 0;
    $zerocont = 0;
    $daycont = 0;
    $dead = 0;
    try {
        /*$reader = Reader::createFromPath('stats.csv', 'r');
        $reader->setHeaderOffset(0);
        $records = Statement::create()->process($reader);
        $gpus = substr_count(implode(" ",$records->getHeader()), "Unit");
        $recordcount = count($reader);
        $json = json_encode($reader);
        $obj = json_decode($json);*/

        $dsn = "mysql:host=localhost;dbname=hiveos";
        $user = "root";
        $passwd = "lolazo34";
        $pdo = new PDO($dsn, $user, $passwd);
        $query = "SELECT * FROM `metrics` WHERE `server_time` BETWEEN '".$contractDateBegin." 00:00:00' AND '".$contractDateEnd." 23:55:00' ORDER BY `server_time`";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetch();
        $rowCount = $stmt->rowCount();
        $gpus = count(explode(',',$rows[0]['ethash']));

        for ($j = 1; $j <= $gpus; $j++) {
            for ($i = 0; $i < $rowCount; $i++) {
                $hashrates = explode(',',$rows[0]['ethash']);
                $gpuHr = $hashrates[$j];
                $daycont++;
                $sum = $sum + $gpuHr;
                if ($gpuHr != 0)
                    $zerocont++;
                else
                    $dead++;
            }
            if ($daycont != 0) {
                $sumavg = $sumavg + ($sum / $daycont);
                array_push($prom, $sum / $daycont);
                array_push($adead,convert_seconds((60 * 5) * ($dead)));
            } else
                print 'Not enough data';
            $zerocont = 0;
            $sum = 0;
            $daycont = 0;
            $dead = 0;
        }

        $resultado['gpuhs'] = $prom;
        $resultado['dead'] = $adead;
        $resultado['income'] = $income;
        $resultado['totalavg'] = $sumavg;
        $resultado['start'] = $contractDateBegin;
        $resultado['end'] = $contractDateEnd;

        $ai = array();
        for ($i = 0; $i < count($prom); $i++) {
            array_push($ai, ($income / $sumavg) * ($prom[$i]));
        }
        $resultado['gpuIncome'] =  $ai;

        $resultado['respuesta'] = true;

        /*
         if($isOwner){
             saveToDB($resultado['income'],json_encode($resultado['gpuIncome']),count($ai),$resultado['start'],$resultado['end'],json_encode($resultado['gpuhs']));
         }
        */

        print json_encode($resultado);
    } catch (Exception $e) {
        $resultado['respuesta'] = false;
        $resultado['error'] = $e;
        print json_encode($resultado);
    }
}
function saveToDB($income,$gpuincome,$gpunum,$start,$payday,$gpuhr) {
    // PDO Connection to MySQL
    $pdo = new PDO('mysql:host=localhost;dbname=lamiel', 'root', 'lolazo34');
    $data = [
        'income' => $income,
        'gpuincome' => $gpuincome,
        'gpunum' => $gpunum,
        'gpuhr' => $gpuhr,
        'start' => $start,
        'payday' => $payday
    ];
    $sql = "INSERT INTO historico (income, gpuincome, gpunum,gpuhr,start,payday) VALUES (:income, :gpuincome, :gpunum,:gpuhr,:start,:payday)";
    $stmt= $pdo->prepare($sql);
    $stmt->execute($data);
}

function convert_seconds($seconds) {
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    return $dtF->diff($dtT)->format('%a days, %h:%i:%s');
}