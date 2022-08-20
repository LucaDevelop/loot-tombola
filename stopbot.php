<?php
error_reporting(0);
include 'vendor/autoload.php';
include 'config.php';

use \React\EventLoop\Factory;
use \unreal4u\TelegramAPI\HttpClientRequestHandler;
use unreal4u\TelegramAPI\Telegram\Methods\GetWebhookInfo;
use unreal4u\TelegramAPI\Telegram\Types\WebhookInfo;
use \unreal4u\TelegramAPI\TgLog;
use \unreal4u\TelegramAPI\Telegram\Methods\DeleteWebhook;

$loop = \React\EventLoop\Factory::create();
$tgLog = new TgLog(TELEGRAM_BOT_KEY, new HttpClientRequestHandler($loop));
$webHookInfo = new GetWebhookInfo();
$promise = $tgLog->performApiRequest($webHookInfo);
$promise->then(
    function (WebhookInfo $info){
        if($info->url != "")
		{
			if(isset($_POST['dbpwd']))
			{
				if($_POST['dbpwd'] == ADMIN_PASSWORD)
				{
					$loop = Factory::create();
					$tgLog = new TgLog(TELEGRAM_BOT_KEY, new HttpClientRequestHandler($loop));
					$delWB = new DeleteWebhook();
						
					$promise = $tgLog->performApiRequest($delWB);
					
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
					<form method="POST" action="stopbot.php">
					<label>Password errata</label>
					<input type="password" name="dbpwd" value="<?php echo $_POST['dbpwd']; ?>" />
					<input type="submit" name="submit" value="Stop" />
					</form>
				<?php
				}
			}
			else
			{
				?>
				<form method="POST" action="stopbot.php">
				<input type="password" name="dbpwd" />
				<input type="submit" name="submit" value="Stop" />
				</form>
			<?php
			}
		}
		else
		{
			echo "<p>Bot gi&agrave; stoppato</p>";
		}
    },
    function (\Exception $e) {
        var_dump($e);
    }
);
$loop->run();