<?php
error_reporting(0);
include 'vendor/autoload.php';
include 'config.php';

use \React\EventLoop\Factory;
use \unreal4u\TelegramAPI\HttpClientRequestHandler;
use unreal4u\TelegramAPI\Telegram\Methods\GetWebhookInfo;
use unreal4u\TelegramAPI\Telegram\Types\WebhookInfo;
use \unreal4u\TelegramAPI\TgLog;
use \unreal4u\TelegramAPI\Telegram\Methods\SetWebhook;

$loop = \React\EventLoop\Factory::create();
$tgLog = new TgLog(TELEGRAM_BOT_KEY, new HttpClientRequestHandler($loop));
if(isset($_POST['dbpwd']))
{
	if($_POST['dbpwd'] == ADMIN_PASSWORD)
	{
		$loop = Factory::create();
		$tgLog = new TgLog(TELEGRAM_BOT_KEY, new HttpClientRequestHandler($loop));
		$getWebhookInfo = new GetWebhookInfo();
			
		$promise = $tgLog->performApiRequest($getWebhookInfo);
		
		$promise->then(function($response){
			echo json_encode($response);
		},
		function(\Exception $exception){
			echo 'Exception ' . get_class($exception) . ' caught, message: ' . $exception->getMessage();
		});
		
		$loop->run();
	}
	else
	{
	?>
		<form method="POST" action="botstatus.php">
		<label>Password errata</label>
		<input type="password" name="dbpwd" value="<?php echo $_POST['dbpwd']; ?>" />
		<input type="submit" name="submit" value="Avvia" />
		</form>
	<?php
	}
}
else
{
	?>
	<form method="POST" action="botstatus.php">
	<input type="password" name="dbpwd" />
	<input type="submit" name="submit" value="Avvia" />
	</form>
<?php
}
$loop->run();