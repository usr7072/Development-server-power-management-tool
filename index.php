<?php
function doFlush(){
	if (!headers_sent()) {
		// Disable gzip in PHP.
		ini_set('zlib.output_compression', 0);
		// Force disable compression in a header.
		// Required for flush in some cases (Apache + mod_proxy, nginx, php-fpm).
		header('Content-Encoding: none');
	}
	// Fill-up 4 kB buffer (should be enough in most cases).
	echo str_pad('', 4 * 1024);
	// Flush all buffers.
	do {
		$flushed = @ob_end_flush();
	} while ($flushed);
	@ob_flush();
	flush();
}

//------------CONFIG------------
include_once('./conf.php');
//------------CONFIG------------

echo "<html>
<head>
<title>学習鯖電源管理ツール　Ver1.0</title>
</head>
<body>
IPMIに接続しています。<br><br>";
//@ob_flush(); // 出力バッファをフラッシュ(送信)する
//flush(); // システム出力バッファをフラッシュする。

doFlush();

exec("ipmitool -I lanplus -H $IPMI_IP -U $IPMI_USER -P $IPMI_PASS power status" , $output , $return_var );
//var_dump($output);
//echo "detteiu $return_var" ;
if($return_var == 0){
    echo "IPMIに接続できました。<br>\n";
    $IPMI_CONNECT = "OK";
    switch($output[0]){
        case 'Chassis Power is off':
        echo 'POWER STATUS:<b><font color=orange>電源OFF</font></b><br>';
        $IPMI_POWER = "OFF";
        if($_SERVER["REQUEST_METHOD"] !== "POST"){
            echo "<form method=\"post\">
           <button type='submit' name='SEND_POWER' value='ON'>電源ONする</button>
            </form>";
        };
        break;
        
        case 'Chassis Power is on':
        echo 'POWER STATUS:<b><font color=green>電源ON</font></b><br>';
        if($_SERVER["REQUEST_METHOD"] !== "POST"){
        echo "<form method=\"post\">
        <button type='submit' name='SEND_POWER' value='OFF'>電源OFFする</button>
        </form>";
        };
        $IPMI_POWER = "ON";
        break;
    }
}else{
    echo "IPMIに接続できませんでした。サーバ管理者へ問い合わせて下さい。<br>
    【考えられる原因】<br>
    ・本ツールにIP、ユーザー、パスの設定を入れていない<br>
    ・通電していない<br>
    ・MGMT接続されていない<br>
    ・サーバちゃんがお亡くなりになっている<br>
    ・サーバ管理者の気分<br>
    ・(。ε゜)ぷえーっ<br><br>\n";
    $IPMI_CONNECT = "NG";
}

$output = NULL;
$return_var = NULL;
if($IPMI_CONNECT == "OK"){
    if($_SERVER["REQUEST_METHOD"] == "POST"){
        if($_POST['SEND_POWER'] == "ON"){
            exec("ipmitool -I lanplus -H $IPMI_IP -U $IPMI_USER -P $IPMI_PASS power on");
            echo "電源ONコマンドを送信しました。<br>";
            doFlush();
            sleep(10);
            exec("ipmitool -I lanplus -H $IPMI_IP -U $IPMI_USER -P $IPMI_PASS power status" , $output , $return_var );

            switch($output[0]){
                case 'Chassis Power is off':
                echo 'POWER STATUS:<b><font color=orange>電源OFF</font></b><br>';
                break;
                
                case 'Chassis Power is on':
                echo 'POWER STATUS:<b><font color=green>電源ON</font></b><br>';
                echo "サーバが起動したか確認しています. ";
                $exec = 1;
                while(exec("ping -c 1 $Server_IP") == false){
                    if($exec == 99){
                        break;
                    }else{
                        $exec = $exec + 1;
                        echo ".";
                        doFlush();    
                    }
                }
                if(exec("ping -c 1 $Server_IP") == true){
                    echo "<br>ふむ、成功かね。";
                }else{
                    echo "<br>ふむ、失敗じゃないかな？";
                }
                break;
            }
        }elseif($_POST['SEND_POWER'] == "OFF"){
            exec("ipmitool -I lanplus -H $IPMI_IP -U $IPMI_USER -P $IPMI_PASS power soft");
            $exec = 1;
            $output = NULL;
            $return_var = NULL;
            echo "シャットダウンしています。処理の途中でブラウザを閉じないでください。<br>オ ";
            doFlush(); 
            while(exec("ipmitool -I lanplus -H $IPMI_IP -U $IPMI_USER -P $IPMI_PASS power status" , $output , $return_var )){
                if($output[0] == "Chassis Power is off"){
                    echo "<br><br>でんげんおちたーーー！！";
                    break;
                }else{
                    $output = NULL;
                    $return_var = NULL;
                    sleep(1);
                    echo "ホ";
                    $exec = $exec + 1;
                    if($exec == 30){
                        echo "<br>なんかフリーズしてるっぽいんで電源強制的に落としますね。<br>";
                        exec("ipmitool -I lanplus -H $IPMI_IP -U $IPMI_USER -P $IPMI_PASS power off");
                        sleep(1);
                        doFlush(); 
                        exec("ipmitool -I lanplus -H $IPMI_IP -U $IPMI_USER -P $IPMI_PASS power status" , $output , $return_var );
                        switch($output[0]){
                            case 'Chassis Power is off':
                            echo 'POWER STATUS:<b><font color=orange>電源OFF</font></b><br>';
                            break;
                            
                            case 'Chassis Power is on':
                            echo 'POWER STATUS:<b><font color=green>電源ON</font></b><br>';
                            break;
                        }
                        break;
                    }
                    doFlush(); 
                }
            }
        }
    }
}
?>