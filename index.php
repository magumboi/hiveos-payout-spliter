
<form enctype='multipart/form-data' action='' method='post'>
    <label>Income</label>

    <input size='20' type='text' name='income' value='<?php if (isset($_POST['income']))print $_POST['income']; ?>'>
    <br>
    <label>Payout date</label>

    <input size='20' type='text' name='date' value='<?php if (isset($_POST['date']))print $_POST['date']; ?>'>
    <br>
    <label>GPUs</label>

    <input size='20' type='text' name='gpu' value='<?php if (isset($_POST['gpu']))print $_POST['gpu']; ?>'>
    <br>
		
<label>Upload Hive OS Stats CSV file Here</label>
 
<input size='50' type='file' name='filename'>
<br>
<input type='submit' name='submit' value='Upload'>
 
</form>
<?php
require 'vendor/autoload.php';
use League\Csv\Reader;

if (isset($_POST['submit'])) {
    if (isset($_POST['income']) && isset($_POST['date']) && isset($_POST['gpu'])) {
        $income = $_POST['income'];
        $gpus = $_POST['gpu'];
        $payday = $_POST['date'];

        $payDayFormat = DateTime::createFromFormat('d/m/Y',$payday);
        $contractDateEnd = date('Y-m-d', strtotime($payDayFormat->format('Y-m-d')));
        $payDayFormat->modify('-2 day');
        $contractDateBegin = date('Y-m-d', strtotime($payDayFormat->format('Y-m-d')));

        /*$income= 0.00886247;
        $gpus=5;
        $payday = "08/10/2021";*/
        
        $prom = array();
        $sumavg = 0;
        $sum = 0;
        $zerocont = 0;
        $daycont = 0;
        $dead = 0;
        try {
            $reader = Reader::createFromPath($_FILES['filename']['tmp_name'], 'r');
            $reader->setHeaderOffset(0);
            $recordcount = count($reader);
            $json = json_encode($reader);
            $obj = json_decode($json);
            for ($j = 1; $j <= $gpus; $j++) {
                for ($i = 0; $i < $recordcount; $i++) {
                    $hashtime = DateTime::createFromFormat('d/m/Y H:i:s', $obj[$i]->{'time'});
                    if ((date('Y-m-d', strtotime($hashtime->format('Y-m-d'))) >= $contractDateBegin) && (date('Y-m-d', strtotime($hashtime->format('Y-m-d'))) < $contractDateEnd)) {
                        $daycont++;
                        $sum = $sum + $obj[$i]->{'Unit ' . $j . ' ethash H/s'};
                        if ($obj[$i]->{'Unit ' . $j . ' ethash H/s'} != 0)
                            $zerocont++;
                        else
                            $dead++;
                    }
                }
                if ($daycont != 0) {
                    print "Average Unit " . $j . " ethash H/s: " . $sum / $zerocont . ' H/s, Downtime: ' . gmdate("H:i:s", (60 * 5) * ($dead)) . '<br>';
                    $sumavg = $sumavg + ($sum / $zerocont);
                    array_push($prom, $sum / $zerocont);
                } else
                    print 'Not enough data<br>';
                $zerocont = 0;
                $sum = 0;
                $daycont = 0;
                $dead = 0;
            }

            print '<br>Income: ' . $income . ' eth<br>';
            print '<br>Total Avg: ' . $sumavg . ' H/s<br>';
            print '<br>';

            for ($i = 0; $i < count($prom); $i++) {
                print "Unit " . ($i + 1) . " income in eth: " . ($income / $sumavg) * ($prom[$i]) . '<br>';
            }

            print '<br>';

            print '<br> Start date: ' . $contractDateBegin;
            print '<br>End date: ' . $contractDateEnd;
        } catch (Exception $e) {
            print $e;
        }
    } else {
        print "Bad Request";
    }
}

