<?php
ini_set("log_errors", 1);
ini_set("error_log", "php-error.log");
include '../vendor/autoload.php';
include '../config.php';

use \React\EventLoop\Factory;
use \unreal4u\TelegramAPI\HttpClientRequestHandler;
use \unreal4u\TelegramAPI\TgLog;
use \unreal4u\TelegramAPI\Telegram\Types\Update;
use \unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\Markup;
use \unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\Button;
use \unreal4u\TelegramAPI\Telegram\Types\InputMessageContent\Text;
use \unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\ButtonInlineAnswer;
use \unreal4u\TelegramAPI\Telegram\Types\InputMedia\Photo;
use \unreal4u\TelegramAPI\Telegram\Types\Inline\Query\Result\Article;
use \unreal4u\TelegramAPI\Telegram\Methods\SendMessage;
use \unreal4u\TelegramAPI\Telegram\Methods\SendPhoto;
use \unreal4u\TelegramAPI\Telegram\Methods\EditMessageText;
use \unreal4u\TelegramAPI\Telegram\Methods\AnswerCallbackQuery;
use \unreal4u\TelegramAPI\Telegram\Methods\SendMediaGroup;
use \unreal4u\TelegramAPI\Telegram\Methods\AnswerInlineQuery;
use \unreal4u\TelegramAPI\Telegram\Methods\EditMessageReplyMarkup;
use \unreal4u\TelegramAPI\Telegram\Methods\DeleteMessage;
use \unreal4u\TelegramAPI\Telegram\Methods\GetChatAdministrators;
use \unreal4u\TelegramAPI\Telegram\Methods\LeaveChat;

use TH\Lock\FileFactory;

$factory = new FileFactory(__DIR__);
$lock = $factory->create('loottombola');

function DBLogin($db_host, $username, $pwd, $database)
{

	$connection = mysqli_connect($db_host,$username,$pwd,$database);

	if(!$connection)
	{
		return false;
	}
	return $connection;
}

$loop = Factory::create();
$tgLog = new TgLog(TELEGRAM_BOT_KEY, new HttpClientRequestHandler($loop));
$updateData = json_decode(file_get_contents('php://input'), true);
$update = new Update($updateData);

if(isset($update->message))
{
	$message = $update->message;
	$chat = $message->chat;
	$chat_id = $chat->id;
	$chattype = $chat->type;
	$user = $message->from;
	$userid = $user->id;
	$username = $user->username;
	$message_id = $message->message_id;
	$msgtxt = $message->text;
	if($chattype == 'supergroup' || $chattype == 'group')
	{
		if($chat_id == (TOMBOLA_TESTING ? TEST_GROUP_ID : GROUP_ID))
		{
			if(in_array($userid, CAN_START_GAME))
			{
				if(startsWith($msgtxt, "/tombola "))
				{
					SendTombola($chat_id, $msgtxt, $tgLog, $loop);
				}
				else if($msgtxt == '/cartellabonus')
				{
					$files = glob(dirname(__FILE__) . "/tbl_*.txt");
					if(count($files) > 0)
					{
						$tblid = str_replace("tbl_","",basename($files[0],".txt"));
						$cartsfiles = glob("cart_".$tblid."_*.txt");
						if(count($cartsfiles) > 0)
						{
							$cartattive = [];
							foreach($cartsfiles as $cf)
							{
								$co = json_decode(file_get_contents($cf), true);
								if($co['attive'] && $co['numbonus'] < 5)
								{
									$cartattive[] = $co;
								}
							}
							$fortunato = random_int(0, count($cartattive) - 1);
							$fortobj = $cartattive[$fortunato];
							$fortobj['cartelle'][] = GeneraCartella();
							$fortobj['numbonus'] += 1;
							file_put_contents("cart_".$tblid."_".$fortobj['userid'].".txt", json_encode($fortobj, JSON_PRETTY_PRINT));
							RefreshIscrizioniTombola($tblid, $tgLog, $loop);
							$sendMessage = new SendMessage();
							$sendMessage->chat_id = $fortobj['userid'];
							$sendMessage->text = "Mentre sei nel salone della 🎅 *Tombola di Natale di LootBot* 🎄 guardi a terra e vedi una cartella caduta che prontamente raccogli.\n*Hai ricevuto una cartella bonus!* Controlla le tue cartelle con il comando /cartelle";
							$sendMessage->parse_mode = "Markdown";
							$promise = $tgLog->performApiRequest($sendMessage);
							$promise->then(
								function () use ($fortobj, $chat_id, $tgLog, $loop){
									$sendMessage = new SendMessage();
									$sendMessage->chat_id = $chat_id;
									$sendMessage->text = "Il fortunato è @".$fortobj['username'];
									$promise = $tgLog->performApiRequest($sendMessage);
									$promise->then(
										function (){
										
										},
										function (\Exception $exception) {
											error_log("[".__LINE__."] ".$exception->getMessage());
										}
									);
									$loop->run();
								},
								function (\Exception $exception) {
									error_log("[".__LINE__."] ".$exception->getMessage());
								}
							);
							$loop->run();
						}
					}
				}
			}
			else
			{
				if($msgtxt == '/cartelle' || $msgtxt == '/cartelle@'.TELEGRAM_BOT_USERNAME)
				{
					SendCartelle($chat_id, $userid, $username, $chattype, $tgLog, $loop, 1);
				}
			}
		}
		if($msgtxt == '/getid' && $userid == ADMIN_CHAT_ID)
		{
			$sendMessage = new SendMessage();
			$sendMessage->chat_id = $chat_id;
			$sendMessage->text = $chat_id;
			$promise = $tgLog->performApiRequest($sendMessage);
			$promise->then(
				function (){
				
				},
				function (\Exception $exception) {
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
				$loop->run();
		}
	}
	else if($chattype == 'private')
	{
		file_put_contents('users/user_'.$userid.'.txt', $username);
		if($msgtxt == '/start')
		{
			$sendMessage = new SendMessage();
			$sendMessage->chat_id = $chat_id;
			$sendMessage->text = "Ciao $username, benvenuto nel ".TELEGRAM_BOT_USERNAME.".";			
			$promise = $tgLog->performApiRequest($sendMessage);
			$promise->then(
				function (){

				},
				function (\Exception $exception) {
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
			$loop->run();
		}
		else if(startsWith($msgtxt, '/start '))
		{
			$arg = str_replace("/start ", "", $msgtxt);
			if(startsWith($arg, "tbl_"))
			{
				$tbl_id = str_replace("tbl_","", $arg);
				if(file_exists("tbl_$tbl_id.txt"))
				{
					if(file_exists("extract_".$tbl_id.".txt"))
					{
						$sendMessage = new SendMessage();
						$sendMessage->chat_id = $chat_id;
						$sendMessage->text = "❌ La tombola è già in corso, non puoi acquistare altre cartelle.";
						$promise = $tgLog->performApiRequest($sendMessage);
						$promise->then(
							function (){
							
							},
							function (\Exception $exception) {
								error_log("[".__LINE__."] ".$exception->getMessage());
							}
						);
						$loop->run();
					}
					else
					{
						if(file_exists("cart_".$tbl_id."_".$userid.".txt"))
						{
							$sendMessage = new SendMessage();
							$sendMessage->chat_id = $chat_id;
							$sendMessage->text = "❌ Hai già acquistato delle cartelle. Per vederle usa il comando /cartelle.";
							$promise = $tgLog->performApiRequest($sendMessage);
							$promise->then(
								function (){
								
								},
								function (\Exception $exception) {
									error_log("[".__LINE__."] ".$exception->getMessage());
								}
							);
							$loop->run();
						}
						else
						{
							$tblobj = json_decode(file_get_contents("tbl_$tbl_id.txt"), true);
							if(GreaterFifty($username, $userid))
							{
								$carts = [];
								for($i=0; $i<5; $i++)
								{
									$carts[] = GeneraCartella();
								}
								$savedcarts = ["tblid" => $tbl_id, "userid" => $userid, "username" => $username, "attive" => true, "cartelle" => $carts, "lastrefresh" => 0, "lastrefreshalert" => 0];
								file_put_contents("cart_".$tbl_id."_".$userid.".txt", json_encode($savedcarts, JSON_PRETTY_PRINT));
								$sendMessage = new SendMessage();
								$sendMessage->chat_id = $chat_id;
								$sendMessage->text = "Ciao ".str_replace("_","\\_",$username).", ecco come funziona la 🎅 *Tombola di Natale di LootBot* 🎄:\n\n".
													"*Acquistare le cartelle:*\n".
													"Per acquistare le cartelle basta premere il pulsante *Partecipa 🏷* sul messaggio nel canale. Verrai rimandato su questo bot e ti verranno generate 5 cartelle.\n".
													"In ogni momento, durante il gioco, si possono vedere le proprie cartelle con il comando /cartelle. Durante l'estrazione verranno evidenziati in rosso i numeri posseduti che sono usciti.\n\n".
													"*Il gioco:*\n".
													"Una volta partito il gioco (all'orario prefissato) il bot si occuperà di estrarre un numero casuale da 1 a 90 ogni 30 secondi.\n".
													"La spunta sulle tue cartelle dei numeri che escono è *AUTOMATICA*, tu non devi fare nulla.\n\n".
													"*I Premi:*\n".
													"Per vince i premi descritti bisogna ottenere prima degli altri giocatori una o più delle seguenti combinazioni:\n".
													"🎗 *Ambo:* Due numeri sulla stessa riga\n".
													"🥉 *Cinquina:* Cinque numeri sulla stessa riga (una riga completa)\n".
													"🥈 *Decina:* Due righe complete sulla stessa cartella\n".
													"🥇 *Tombola:* una cartella tutta completa\n".
													"*NB:* Se un premio viene vinto da più giocatori contemporaneamente il vincitore finale sarà estratto a sorte.\n".
													(!ALLOW_MULTIPLE_WINNINGS ? "*NBB:* Se durante la tombola vinci un premio per te l'estrazione termina e non avrai la possibilità di vincerne altri.\n":"").
													"\n".
													"✅  Sono state generate 5 cartelle per te.";
								$sendMessage->parse_mode = "Markdown";
								$inlineKeyboard = new Markup();
								$sendMessage->reply_markup = $inlineKeyboard;
								$promise = $tgLog->performApiRequest($sendMessage);
								$promise->then(
									function () {
									},
									function (\Exception $exception) {
										error_log("[".__LINE__."] ".$exception->getMessage());
									}
								);
								$loop->run();
								RefreshIscrizioniTombola($tbl_id, $tgLog, $loop);
							}
							else
							{
								$sendMessage = new SendMessage();
								$sendMessage->chat_id = $chat_id;
								$sendMessage->text = "❌ Ciao ".str_replace("_","\\_",$username).", purtoppo non puoi partecipare perché non sei un giocatore di Loot Bot.\nSe pensi che questo messaggio sia un errore contattami in privato (@".ADMIN_USERNAME.").";
								$sendMessage->parse_mode = "Markdown";					
								$promise = $tgLog->performApiRequest($sendMessage);
								$promise->then(
									function () {
									},
									function (\Exception $exception) {
										error_log("[".__LINE__."] ".$exception->getMessage());
									}
								);
								$loop->run();
							}												
						}
					}
				}
			}
			else if(startsWith($arg, "cart_"))
			{
				SendCartelle($chat_id, $userid, $username, $chattype, $tgLog, $loop, 1);
			}
		}
		else if($msgtxt == '/cartelle')
		{
			SendCartelle($chat_id, $userid, $username, $chattype, $tgLog, $loop, 1);
		}
		else if(startsWith($msgtxt, "/tombola ") && in_array($userid, CAN_START_GAME))
		{
			SendTombola($chat_id, $msgtxt, $tgLog, $loop);
		}
		else if(startsWith($msgtxt, "/cartella ") && in_array($userid, CAN_START_GAME))
		{
			$parstr = str_replace("/cartella ","", $msgtxt);
			$parobj = explode(",", $parstr);
			$sendPhoto = new SendPhoto();
			$sendPhoto->chat_id = $chat_id;
			$sendPhoto->photo = IMAGES_SCRIPT_BASE_URL."makecart.php?tblid=".$parobj[0]."&userid=".$parobj[1]."&idx=".$parobj[2]."&anticache=".RandomID();
			$promise = $tgLog->performApiRequest($sendPhoto);
			$promise->then(
			function ($response){
				},
				function (\Exception $exception) {
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
			$loop->run();
		}
		else if($msgtxt == '/refreshtombola' && in_array($userid, CAN_START_GAME))
		{
			$contrem = 0;
			$files = glob('tbl_*.txt');
			if(count($files) == 1)
			{
				$tblid = str_replace("tbl_","",basename($files[0],".txt"));
				RefreshIscrizioniTombola($tblid, $tgLog, $loop);
				$sendMessage = new SendMessage();
				$sendMessage->chat_id = $chat_id;
				$sendMessage->text = "Tombola $tblid refreshata";
				$promise = $tgLog->performApiRequest($sendMessage);
				$promise->then(
					function (){
					
					},
					function (\Exception $exception) {
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
			else
			{
				$sendMessage = new SendMessage();
				$sendMessage->chat_id = $chat_id;
				$sendMessage->text = "Non ci sono tombole attive";
				$promise = $tgLog->performApiRequest($sendMessage);
				$promise->then(
					function (){
					
					},
					function (\Exception $exception) {
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
		}
		else if(startsWith($msgtxt, "/libera ") && in_array($userid, CAN_START_GAME)) //Rifare con username
		{
			$usertofree = str_replace("/libera ", "", $msgtxt);
			file_put_contents('except_'.$usertofree.'.cfg',time());
			$sendMessage = new SendMessage();
			$sendMessage->chat_id = $chat_id;
			$sendMessage->text = "$usertofree liberato";
			$promise = $tgLog->performApiRequest($sendMessage);
			$promise->then(
				function (){
		
				},
				function (\Exception $exception) {
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
			$loop->run();
		}
		else if($msgtxt == '/clean' && in_array($userid, CAN_START_GAME))
		{
			$files = glob(dirname(__FILE__) . "/*.{lock,txt}", GLOB_BRACE);
			$fc = count($files);
			foreach($files as $f)
			{
				unlink($f);
			}
			SendAdmin("Puliti $fc files",$loop, $tgLog);
		}
		else if(startsWith($msgtxt, "/simula ") && in_array($userid, CAN_START_GAME))
		{
			$howmany = str_replace('/simula ','',$msgtxt);
			$files = glob("tbl*.txt");
			if(count($files) > 0)
			{
				$tblid = str_replace("tbl_","",basename($files[0],".txt"));
				for($n=0; $n<$howmany; $n++)
				{
					$carts = [];
					for($i=0; $i<5; $i++)
					{
						$carts[] = GeneraCartella();
					}
					$sponsorcode = uniqid();
					$ranuid = random_int(10000000, 999999999)*-1;
					$savedcarts = ["tblid" => $tblid, "userid" => $ranuid, "username" => RandomID(), "attive" => true, "cartelle" => $carts, "lastrefresh" => 0, "lastrefreshalert" => 0];
					file_put_contents("cart_".$tblid."_".$ranuid.".txt", json_encode($savedcarts, JSON_PRETTY_PRINT));
				}
				RefreshIscrizioniTombola($tblid, $tgLog, $loop);
				$sendMessage = new SendMessage();
				$sendMessage->chat_id = $chat_id;
				$sendMessage->text = "Generati $howmany partecipanti";
				$promise = $tgLog->performApiRequest($sendMessage);
				$promise->then(
					function (){
					
					},
					function (\Exception $exception) {
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
		}
		else if(startsWith($msgtxt, "/getinfoid ") && in_array($userid, CAN_START_GAME))
		{
			$idtocheck = str_replace("/getinfoid ", "", $msgtxt);
			$boturl = "https://api.telegram.org/bot".TELEGRAM_BOT_KEY."/getChatMember";
			$bodyobj = ["chat_id" => CHANNEL_NAME, "user_id" => $idtocheck];
			$ch = curl_init();
						
			curl_setopt($ch, CURLOPT_URL,            $boturl );
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt($ch, CURLOPT_POST,           1 );
			curl_setopt($ch, CURLOPT_POSTFIELDS,     $bodyobj); 
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:multipart/form-data']);
						
			$result=curl_exec ($ch);
			$resobj = json_decode($result, true);
			$sendMessage = new SendMessage();
			$sendMessage->chat_id = $chat_id;
			$sendMessage->text = json_encode($resobj, JSON_PRETTY_PRINT);
			$promise = $tgLog->performApiRequest($sendMessage);
			$promise->then(
				function (){
				
				},
				function (\Exception $exception) {
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
			$loop->run();
		}
		else if($msgtxt == '/tombola')
		{
			$files = glob("tbl*.txt");
			if(count($files) > 0)
			{
				$tblid = str_replace("tbl_","",basename($files[0],".txt"));
				$tblobj = json_decode(file_get_contents($files[0]), true);
				$sendMessage = new SendMessage();
				$sendMessage->chat_id = $chat_id;
				$sendMessage->parse_mode = "Markdown";
				$sendMessage->text = $tblobj['text'];
				$inlineKeyboard = new Markup();
				$inlineKeyboardButton = new Button();
				$inlineKeyboardButton->text = "Visualizza links invito";
				$inlineKeyboardButton->callback_data = "showpubbl_".$tblid;
				$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
				$sendMessage->reply_markup = $inlineKeyboard;
				$promise = $tgLog->performApiRequest($sendMessage);
				$promise->then(
					function (){
					
					},
					function (\Exception $exception) {
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
			else
			{
				$sendMessage = new SendMessage();
				$sendMessage->chat_id = $chat_id;
				$sendMessage->text = "❌ Non ci sono tombole in corso al momento.";
				$promise = $tgLog->performApiRequest($sendMessage);
				$promise->then(
					function (){
					
					},
					function (\Exception $exception) {
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
		}
		else if($msgtxt == '/getid')
		{
			$sendMessage = new SendMessage();
			$sendMessage->chat_id = $chat_id;
			$sendMessage->text = $chat_id;
			$promise = $tgLog->performApiRequest($sendMessage);
			$promise->then(
				function (){
				
				},
				function (\Exception $exception) {
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
				$loop->run();
		}
	}
}
else if(isset($update->channel_post))
{
	//error_log(json_encode($update, JSON_PRETTY_PRINT));
	$channel_post = $update->channel_post;
	$msgtxt = $channel_post->text;
	$chat = $channel_post->chat;
	$chat_id = $chat->id;
	if($msgtxt == '/getchatid' || $msgtxt == '/getchatid@'.TELEGRAM_BOT_USERNAME)
	{
		$sendMessage = new SendMessage();
		$sendMessage->chat_id = $chat_id;
		$sendMessage->text = $chat_id;
		$promise = $tgLog->performApiRequest($sendMessage);
		$promise->then(
			function (){
			
			},
			function (\Exception $exception) {
				error_log("[".__LINE__."] ".$exception->getMessage());
			}
		);
		$loop->run();
	}
}
else if(isset($update->callback_query))
{
	$callback = $update->callback_query;
	$cbid = $callback->id;
	$user = $callback->from;
	$username = $user->username;
	$userid = $user->id;
	$cbmess = $callback->message;
	$cbchat = (!isset($callback->message) || $callback->message == null ? null : $cbmess->chat);
	$cbchattype = (!isset($callback->message) || $callback->message == null ? null : $cbchat->type);
	$cbresp = $callback->data;
	file_put_contents('users/user_'.$userid.'.txt', $username);
	if(startsWith($cbresp, "cartrefresh_"))
	{
		$carinf = explode("_", $cbresp, 3);
		$chkusrid = $carinf[2];
		if(file_exists("tbl_".$carinf[1].".txt"))
		{
			if($userid == $chkusrid)
			{
				if(file_exists("cart_".$carinf[1]."_".$userid.".txt"))
				{
					$cartobj = json_decode(file_get_contents("cart_".$carinf[1]."_".$userid.".txt"), true);
					$now = time();
					if($cartobj['lastrefresh'] == 0 || ($now - $cartobj['lastrefresh']) > 30)
					{
						$cpttxt = "";
						if($cbchattype != 'private')
						{
							$cpttxt .= $user->username.", ecco le tue cartelle.";
						}
						if(isset($cartobj['lobby']))
						{
							$cpttxt .= "\nLobby ".($cartobj['lobby']+1);
						}
						EditCart ($cbchat->id, $cbmess->message_id, IMAGES_SCRIPT_BASE_URL."makecartgrp.php?tblid=".$carinf[1]."&userid=".$userid."&page=1&anticache=".RandomID(), $cpttxt, "cartrefresh_".$carinf[1]."_".$userid);
						$answerCallbackQuery = new AnswerCallbackQuery();
						$answerCallbackQuery->callback_query_id = $cbid;
						$answerCallbackQuery->text = "✅ Cartelle aggiornate.";
						$promise = $tgLog->performApiRequest($answerCallbackQuery);
						$promise->then(
							function () {
							},
							function (\Exception $exception){
								error_log("[".__LINE__."] ".$exception->getMessage());
							}
						);
						$loop->run();
						$cartobj['lastrefresh'] = $now;
						$cartobj['lastrefreshalert'] = 0;
						file_put_contents("cart_".$carinf[1]."_".$userid.".txt", json_encode($cartobj, JSON_PRETTY_PRINT));
					}
					else
					{
						if($cartobj['lastrefreshalert'] == 0)
						{
							$answerCallbackQuery = new AnswerCallbackQuery();
							$answerCallbackQuery->callback_query_id = $cbid;
							$answerCallbackQuery->text = "⏱ Eh no! Aspetta almeno 30 secondi tra un refresh e l'altro. Io e il mio server te ne saremo grati.";
							$answerCallbackQuery->cache_time = $now - $cartobj['lastrefresh'];
							$answerCallbackQuery->show_alert = true;
							$promise = $tgLog->performApiRequest($answerCallbackQuery);
							$promise->then(
								function () {
								},
								function (\Exception $exception){
									error_log("[".__LINE__."] ".$exception->getMessage());
								}
							);
							$loop->run();
							$cartobj['lastrefreshalert'] = $now;
							file_put_contents("cart_".$carinf[1]."_".$userid.".txt", json_encode($cartobj, JSON_PRETTY_PRINT));
						}
					}
				}
				else
				{
					$answerCallbackQuery = new AnswerCallbackQuery();
					$answerCallbackQuery->callback_query_id = $cbid;
					$answerCallbackQuery->text = "❌ Tombola conclusa, impossibile aggiornare";
					$answerCallbackQuery->cache_time = $now - $cartobj['lastrefresh'];
					$answerCallbackQuery->show_alert = true;
					$promise = $tgLog->performApiRequest($answerCallbackQuery);
					$promise->then(
						function () {
						},
						function (\Exception $exception){
							error_log("[".__LINE__."] ".$exception->getMessage());
						}
					);
					$loop->run();
				}
			}
			else
			{
				$answerCallbackQuery = new AnswerCallbackQuery();
				$answerCallbackQuery->callback_query_id = $cbid;
				$answerCallbackQuery->text = "❌ Queste non sono le tue cartelle";
				$answerCallbackQuery->show_alert = true;
				$promise = $tgLog->performApiRequest($answerCallbackQuery);
				$promise->then(
					function () {
					},
					function (\Exception $exception){
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
		}
		else
		{
			$answerCallbackQuery = new AnswerCallbackQuery();
			$answerCallbackQuery->callback_query_id = $cbid;
			$answerCallbackQuery->text = "❌ Tombola conclusa, impossibile aggiornare";
			$answerCallbackQuery->cache_time = 0;
			$answerCallbackQuery->show_alert = true;
			$promise = $tgLog->performApiRequest($answerCallbackQuery);
			$promise->then(
				function () {
				},
				function (\Exception $exception){
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
			$loop->run();
		}
	}
	else if($cbresp == 'none')
	{
		$answerCallbackQuery = new AnswerCallbackQuery();
		$answerCallbackQuery->callback_query_id = $cbid;
		$answerCallbackQuery->cache_time = 0;
		$promise = $tgLog->performApiRequest($answerCallbackQuery);
		$promise->then(
			function () {
			},
			function (\Exception $exception){
				error_log("[".__LINE__."] ".$exception->getMessage());
			}
		);
		$loop->run();
	}
}

function SendTombola($chat_id, $msgtxt, &$tgLog, &$loop)
{
	$files = glob("tbl_*.txt");
	if(count($files) == 0)
	{
		$parstr = str_replace("/tombola ","", $msgtxt);
		$parobj = explode(",", $parstr);
		$costo = 0;
		$ambo = "";
		$cinquina = "";
		$decina = "";
		$tombola = "";
		if(count($parobj) == 5)
		{
			$tbl_id = RandomID();					
			$ambo = str_replace("\\n","\n",str_replace("\\t","\t", $parobj[0]));
			$cinquina = str_replace("\\n","\n",str_replace("\\t","\t", $parobj[1]));
			$decina = str_replace("\\n","\n",str_replace("\\t","\t", $parobj[2]));
			$tombola = str_replace("\\n","\n",str_replace("\\t","\t", $parobj[3]));
			$ore = $parobj[4];
			$ok = false;
			if(preg_match('/^(0[0-9]|1[0-9]|2[0-3]):(0[0-9]|[1-4][0-9]|5[0-9])$/', $ore) === 1)
			{
				$ok = true;
			}
			else
			{
				$ok = false;
			}
			if($ok)
			{
				$files = glob("cart_*.txt");
				foreach($files as $f)
				{
					unlink($f);
				}
				$files = glob("infotbl_*.txt");
				foreach($files as $f)
				{
					unlink($f);
				}
				$files = glob("extract_*.txt");
				foreach($files as $f)
				{
					unlink($f);
				}
				$files = glob("pubbl_*.txt");
				foreach($files as $f)
				{
					unlink($f);
				}
				$sendMessage = new SendMessage();
				$sendMessage->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
				$tombolatxt = "🎅 *Tombola di Natale di LootBot* 🎄\n\n".
								"🎁 _Vincite:_\n".
								"🎗 Ambo: *$ambo*\n".
								"🥉 Cinquina: *$cinquina*\n".
								"🥈 Decina: *$decina*\n".
								"🥇 Tombola: *$tombola*\n\n".
								"⏰ Orario di inizio: *$ore*\n\n".
								"_NB: L'estrazione dei numeri e le spunte sulle cartelle sono totalmente automatiche. L'unica azione manuale richiesta è la registrazione._";
				$sendMessage->text = $tombolatxt."\n\n_Partecipanti: 0\nCartelle attive: 0_";
				$inlineKeyboard = new Markup();
				$inlineKeyboardButton = new Button();
				$inlineKeyboardButton->text = "Partecipa 🏷";
				$inlineKeyboardButton->url = "https://t.me/".TELEGRAM_BOT_USERNAME."?start=tbl_$tbl_id";
				$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
				$sendMessage->reply_markup = $inlineKeyboard;
				$sendMessage->parse_mode = "Markdown";
				$promise = $tgLog->performApiRequest($sendMessage);
				$promise->then(
				function ($response) use ($tombolatxt, $tbl_id, $costo, $ambo, $cinquina, $decina, $tombola, $ore, $tgLog, $loop){
						$tblobj = ["msgid" => $response->message_id, "text" => $tombolatxt, "costo" => $costo, "ambo" => $ambo, "cinquina" => $cinquina, "decina" => $decina, "tombola" => $tombola, "created" => time()];
						$tblobj['ore'] = $ore;
						file_put_contents("tbl_$tbl_id.txt", json_encode($tblobj));
						sleep(1);
						$editMessageReplyMarkup = new EditMessageReplyMarkup();
						$editMessageReplyMarkup->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
						$editMessageReplyMarkup->message_id = $response->message_id;
						$inlineKeyboard = new Markup();
						$inlineKeyboardButton = new Button();
						$inlineKeyboardButton->text = "Partecipa 🏷";
						$inlineKeyboardButton->url = "https://t.me/".TELEGRAM_BOT_USERNAME."?start=tbl_$tbl_id";
						$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
						$inlineKeyboardButton = new Button();
						$inlineKeyboardButton->text = "Condividi 🗣";
						$inlineKeyboardButton->url = "https://t.me/share/url?url=".htmlentities("https://t.me/".str_replace('@','', (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME))."/".$response->message_id);
						$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
						$editMessageReplyMarkup->reply_markup = $inlineKeyboard;
						$promise = $tgLog->performApiRequest($editMessageReplyMarkup);
						$promise->then(
						function () {},
							function (\Exception $exception) {
								error_log("[".__LINE__."] ".$exception->getMessage());
							}
						);
						$loop->run();
					},
					function (\Exception $exception) {
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
		}
	}
	else
	{
		$sendMessage = new SendMessage();
		$sendMessage->chat_id = $chat_id;
		$sendMessage->text = "E' già presente una tombola in corso.";
		$promise = $tgLog->performApiRequest($sendMessage);
		$promise->then(
			function (){

			},
			function (\Exception $exception) {
				error_log("[".__LINE__."] ".$exception->getMessage());
			}
		);
		$loop->run();
	}
}

function SendCartelle($chat_id, $userid, $username, $chattype, &$tgLog, &$loop, $numpag)
{
	$files = glob("tbl_*.txt");
	if(count($files) == 1)
	{
		$tblid = str_replace("tbl_","",basename($files[0],".txt"));
		if(file_exists("cart_".$tblid."_".$userid.".txt"))
		{
			$cartsobj = json_decode(file_get_contents("cart_".$tblid."_".$userid.".txt"), true);
			$now = time();
			if($cartsobj['lastrefresh'] == 0 || ($now - $cartsobj['lastrefresh']) > 30)
			{
				$sendPhoto = new SendPhoto();
				$sendPhoto->chat_id = $chat_id;
				$sendPhoto->photo = IMAGES_SCRIPT_BASE_URL."makecartgrp.php?tblid=".$tblid."&userid=".$userid."&page=$numpag&anticache=".RandomID();
				$cpttxt = "";
				if($chattype != 'private')
				{
					$cpttxt .= $username.", ecco le tue cartelle.";
				}
				if(isset($cartsobj['lobby']))
				{
					$cpttxt .= "\nLobby ".($cartsobj['lobby']+1);
				}
				if(isset($cartsobj['lobby']) && !ALLOW_MULTIPLE_WINNINGS && HadWon($tblid, $cartsobj['lobby'], $username))
				{
					$cpttxt .= "\n⚠️ Hai già vinto un premio. Non stai più partecipando all'estrazione.";
				}
				if(strlen($cpttxt) > 0)
				{
					$sendPhoto->caption = $cpttxt;
				}
				$inlineKeyboard = new Markup();
				$inlineKeyboardButton = new Button();
				$inlineKeyboardButton->text = "Aggiorna 🔄";
				$inlineKeyboardButton->callback_data = "cartrefresh_".$tblid."_".$userid;
				$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
				$sendPhoto->reply_markup = $inlineKeyboard;
				$promise = $tgLog->performApiRequest($sendPhoto);
				$promise->then(
				function ($response){
					//error_log("[".__LINE__."] Inviate");
					},
					function (\Exception $exception) {
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
				$cartsobj['lastrefresh'] = $now;
				$cartsobj['lastrefreshalert'] = 0;
				file_put_contents("cart_".$tblid."_".$userid.".txt", json_encode($cartsobj, JSON_PRETTY_PRINT));
			}
			else
			{
				if($cartsobj['lastrefreshalert'] == 0)
				{
					$sendMessage = new SendMessage();
					$sendMessage->chat_id = $userid;
					$sendMessage->text = "⏱ Eh no! Aspetta almeno 30 secondi tra un refresh e l'altro. Io e il mio server te ne saremo grati.";
					$promise = $tgLog->performApiRequest($sendMessage);
					$promise->then(
						function (){
						
						},
						function (\Exception $exception) {
							error_log("[".__LINE__."] ".$exception->getMessage());
						}
					);
					$loop->run();
					$cartsobj['lastrefreshalert'] = $now;
					file_put_contents("cart_".$tblid."_".$userid.".txt", json_encode($cartsobj, JSON_PRETTY_PRINT));
				}
			}
		}
		else
		{
			$sendMessage = new SendMessage();
			$sendMessage->chat_id = $userid;
			$sendMessage->text = "❌ Non hai ancora acquistato delle cartelle. Per farlo clicca \"Partecipa\" sul messaggio di inzio tombola sul canale.";
			$promise = $tgLog->performApiRequest($sendMessage);
			$promise->then(
				function (){
				
				},
				function (\Exception $exception) {
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
			$loop->run();
		}
	}
	else
	{
		$files = glob("cart_*_".$userid.".txt");
		if(count($files) == 1)
		{
			$bn = basename($files[0], '.txt');
			$bns = explode('_', $bn, 3);
			if(file_exists('extract_'.$bns[1].'.txt'))
			{
				$cartobj = json_decode(file_get_contents($files[0]), true);
				$sendPhoto = new SendPhoto();
				$sendPhoto->chat_id = $chat_id;
				$sendPhoto->photo = IMAGES_SCRIPT_BASE_URL."makecartgrp.php?tblid=".$bns[1]."&userid=".$userid."&anticache=".RandomID();
				if($chattype != 'private')
				{
					$sendPhoto->caption = $username.", ecco le tue cartelle finita la tombola.";
				}
				$promise = $tgLog->performApiRequest($sendPhoto);
				$promise->then(
					function ($response) use ($bns, $userid, $cartobj, $chat_id, $chattype, $username, $tgLog, $loop){
						$sendPhoto = new SendPhoto();
						$sendPhoto->chat_id = $chat_id;
						$sendPhoto->photo = IMAGES_SCRIPT_BASE_URL."makecartgrp.php?tblid=".$bns[1]."&userid=".$userid."&anticache=".RandomID();
						if($chattype != 'private')
						{
							$sendPhoto->caption = $username.", ecco le tue cartelle finita la tombola.";
						}
						$promise = $tgLog->performApiRequest($sendPhoto);
						$promise->then(
							function ($response) use ($bns, $userid, $cartobj){
								unlink("cart_".$bns[1]."_".$userid.".txt");
							},
							function (\Exception $exception) {
								error_log("[".__LINE__."] ".$exception->getMessage());
							}
						);
						$loop->run();
					},
					function (\Exception $exception) {
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
		}
		else
		{
			$sendMessage = new SendMessage();
			$sendMessage->chat_id = $userid;
			$sendMessage->text = "❌ Non ci sono tombole in corso ora.";
			$promise = $tgLog->performApiRequest($sendMessage);
			$promise->then(
				function (){
				
				},
				function (\Exception $exception) {
					error_log("[".__LINE__."] ".$exception->getMessage());
				}
			);
			$loop->run();
		}
	}
}

function GeneraCartella()
{
	$cartella = [];			//Array che contiene tutti i numeri per la semplice esclusione di quelli già usciti e il conteggio totale
	$rows = [[],[],[]];		//Cartella suddivisa in 3 righe. Verrà restituito questa variabile dal metodo
	$groups = [];			//Array per la gestione delle colonne in modo da controllare che vengano estratti al massimo 3 numeri per colonna
	while(count($cartella) < 15)	//Ciclo finché non sono stati estratti tutti e 15 i numeri di una cartella
	{
		$n = random_int(1, 90);			//estraggo un numero casuale da 1 a 90
		if(!in_array($n, $cartella))	//Se non è già stato estratto allora posso prenderlo in considerazione
		{
			$group = GroupCalc($n);		//Ne calcolo il "gruppo" (cioè di che colonna della cartella fa parte)
			if(isset($groups[$group])) //Se esistono già numeri per quel gruppo
			{
				if($groups[$group] < 3)	//Se in quel gruppo non ci sono già tre numeri allora posso aggiungerlo
				{
					for($i=0; $i<3; $i++)	//Ciclo su tutte le righe della tabella
					{
						if(count($rows[$i]) < 5)	//Se la riga ha meno di 5 numeri (perché di più non ne può tenere)
						{
							$toadd = true;			//Setto una variabile di controllo a true
							for($y=0; $y<count($rows[$i]); $y++)	//Ciclo sui numeri già presenti nella riga corrente
							{
								$rgrp = GroupCalc($rows[$i][$y]);	//Calcolo il gruppo del numero
								if($rgrp == $group)					//Se il gruppo del numero estratto è uguale a quello di un numero già presente
								{
									$toadd = false;					//Il numero non può essere aggiunto perché per ogni riga ci può essere solo un numero per gruppo
									break;
								}
							}
							if($toadd)					//Se il gruppo del numero non era già presente su questa riga
							{
								$rows[$i][] = $n;		//Aggiungo il numero alla riga
								$cartella[] = $n;		//Aggiungo il numero tra quelli aggiunti alla cartella
								$groups[$group]++;		//Incremento di uno i numeri inseriti per quel gruppo (sempre per il concetto che massimo 3 per gruppo)
								break;
							}
						}
					}					
				}				
			}
			else	//Se numeri di quel gruppo non ne sono mai stati inseriti
			{
				for($i=0; $i<3; $i++)	//Ciclo tutte le righe
				{
					if(count($rows[$i]) < 5)	//La prima con meno di 5 numeri inserisco
					{
						$rows[$i][] = $n;	//Aggiungo il numero alla riga
						$cartella[] = $n;	//Aggiungo il numero tra quelli aggiunti alla cartella
						$groups[$group] = 1;	//Inserisco il gruppo nell'array dei gruppi con lavore 1 (perché il numero è di un gruppo mai inserito prima)
						break;
					}
				}
			}
		}
	}
	sort($rows[0]);	//Metto in ordine le righe, sistemando l'ordinamento orizzontale
	sort($rows[1]);
	sort($rows[2]);
	//Ora sistemo l'oridnamento verticale
	$done = false;	//Variabile di controllo. Devo fermarmi so quando non ci sono stati scambi da fare
	while(!$done) //Finché ci sono stati scambi da fare
	{
		$done = true;	//Setto a true la variabile. Se non ci saranno scambi rimarrà a true e il ciclo terminerà (stile bubble sort)
		for($r=0;$r<2;$r++) //Ciclo per la prima e la seconda riga
		{
			for($i=0;$i<5;$i++)	//ciclo ogni numero della riga
			{
				$r1 = $rows[$r][$i];	//Metto il numero in una variabile
				$g1 = GroupCalc($r1);	//Ne calcolo il gruppo
				for($y=0;$y<5;$y++)		//Ciclo per ogni numero della riga successiva (quindi, alla fine del ciclo superiore avrò confrontato la prima riga con la seconda e la seconda con la terza)
				{
					$r2 = $rows[$r+1][$y];	//Metto in una variabile il numero
					$g2 = GroupCalc($r2);	//Ne calcolo il gruppo
					if($g1 == $g2)			//Se i due numeri hanno il gruppo uguale devo controllare
					{
						if($r2 < $r1)		//Se il numero della riga successiva è minore della precedente sono da scambiare (per l'ordinamento verticale)
						{
							$rows[$r][$i] = $r2;	//Metto il numero della riga successiva al posto di quello nella riga precedente
							$rows[$r+1][$y] = $r1;	//Metto il numero della riga precedente al posto di quello nella riga successiva
							$done = false;			//Setto la variabile a false per continuare il ciclo while (almeno uno scambio è stato operato)
							break;
						}
					}
				}
			}
		}
		// Nel ciclo precedente ho confrontato la prima riga con la seconda e la seconda con la terza. Qui controllo la prima con la terza
		for($i=0;$i<5;$i++)	//Ciclo i numeri della prima riga
		{
			$r1 = $rows[0][$i];	//Metto il numero della prima riga in una variabile
			$g1 = GroupCalc($r1);	//Ne calcolo il gruppo
			for($y=0;$y<5;$y++)		//Ciclo i numeri della terza riga
			{
				$r2 = $rows[2][$y];	//Metto il numero della terza riga in una variabile
				$g2 = GroupCalc($r2);	//Ne calcolo il gruppo
				if($g1 == $g2)			//Se i due numeri hanno il gruppo uguale devo controllare
				{
					if($r2 < $r1)		//Se il numero della terza riga è minore del numero della prima riga sono da scambiare (per l'ordinamento verticale)
					{
						$rows[0][$i] = $r2;	//Metto il numero della terza riga al posto di quello della prima riga
						$rows[2][$y] = $r1;	//Metto il numero della prima riga al posto di quello della terza riga
						$done = false;	//Setto la variabile a false per continuare il ciclo while (almeno uno scambio è stato operato)
						break;
					}
				}
			}
		}
	}
	return $rows;	//Ritorno l'array di tre righe di numeri che rappresenta la cartella
}

function RefreshIscrizioniTombola($tblid, &$tgLog, &$loop, $numtry = 0)
{
	if(file_exists("tbl_".$tblid.".txt"))
	{
		if(lock())
		{
			$dorefresh = true;
			if(file_exists('tombolarefresh.txt'))
			{
				$lastrefresh = file_get_contents('tombolarefresh.txt');
				if(time() - $lastrefresh < 5)
				{
					$dorefresh = false;
				}
			}
			if($dorefresh)
			{
				$tblobj = json_decode(file_get_contents("tbl_".$tblid.".txt"), true);
				$parts = 0;
				$carts = 0;
				$files = glob("cart_".$tblid."_*.txt");
				foreach($files as $f)
				{
					$cf = json_decode(file_get_contents($f), true);
					$parts++;
					$carts += count($cf['cartelle']);
				}
				$editMessageText = new EditMessageText();
				$editMessageText->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
				$editMessageText->message_id = $tblobj['msgid'];
				$editMessageText->text = $tblobj['text']."\n\n_Partecipanti: ".number_format($parts,0,"",".")."\nCartelle attive: ".number_format($carts,0,"",".")."_";
				$inlineKeyboard = new Markup();
				$inlineKeyboardButton = new Button();
				$inlineKeyboardButton->text = "Partecipa 🏷";
				$inlineKeyboardButton->url = "https://t.me/".TELEGRAM_BOT_USERNAME."?start=tbl_$tblid";
				$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
				$inlineKeyboardButton = new Button();
				$inlineKeyboardButton->text = "Condividi 🗣";
				$inlineKeyboardButton->url = "https://t.me/share/url?url=".htmlentities("https://t.me/".str_replace('@','', (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME))."/".$tblobj['msgid']);
				$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
				$editMessageText->reply_markup = $inlineKeyboard;
				$editMessageText->parse_mode = "Markdown";
				$promise = $tgLog->performApiRequest($editMessageText);
				$promise->then(
				function () {
					file_put_contents('tombolarefresh.txt', time());
					},
					function (\Exception $exception) use ($editMessageText, $tblid, $tgLog, $loop, $numtry){
						if($numtry < 10)
						{
							$newnumtry = $numtry+1;
							$exctxt = $exception->getMessage();
							$mtch = [];
							$resmt = preg_match('/\d+$/', $exctxt, $mtch);
							if($resmt === 1 && startsWith($exctxt, "Too Many Requests"))
							{
								$secwait = $mtch[0];
								sleep($secwait);
								RefreshIscrizioniTombola($tblid, $tgLog, $loop, $newnumtry);
							}
							else
							{
								LogAndSegnala($editMessageText, $exception->getMessage(), __LINE__, $loop, $tgLog);
							}
						}
						else
						{
							//SendAdmin("Limite di retry raggiunto", $loop, $tgLog);
						}
					}
				);
				$loop->run();
			}
			unlock();
		}
		else
		{
			error_log('webhook - lockato');
		}
	}
}


function GreaterFifty($username, $userid)
{
	$res = false;
	if(file_exists('except_'.$userid.'.cfg'))
	{
		$res = true;
	}
	else{
		$apires = richiestaAPI("https://fenixweb.net:6600/api/v2/".LOOT_API_TOKEN."/players/".htmlspecialchars($username));
		$apiobj = json_decode($apires, true);
		if($apiobj !== null)
		{
			if(isset($apiobj['code']))
			{
				if($apiobj['code'] == 200)
				{
					if(isset($apiobj['res'][0]['greater_50']))
					{
						$res = true;
					}
					else
					{
						error_log("[".__LINE__."] - ".$apires);
					}
				}
				else
				{
					error_log("[".__LINE__."] - ".$apires);
				}
			}
			else
			{
				error_log("[".__LINE__."] - ".$apires);
			}
		}
		else
		{
			error_log("[".__LINE__."] - ".json_last_error_msg());
		}
	}
	return $res;
}

function GroupCalc($n)
{
	return ($n == 90 ? 8 : floor($n / 10));
}

function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function randLetter()
{
	$int = random_int(0,25);
	$a_z = "abcdefghijklmnopqrstuvwxyz";
	$rand_letter = $a_z[$int];
	return $rand_letter;
}

function RandomID()
{
	$tbl_id="";
	for($i=0;$i<10;$i++)
	{
		$tbl_id .= randLetter();
	}
	return $tbl_id;
}

function cmp($a, $b)
{
	$anum = $a["num"];
	$bnum = $b["num"];
    if ($anum == $bnum) {
        return 0;
    }
    return ($anum < $bnum) ? -1 : 1;
}

function LogAndSegnala(&$actionObj, $errtext, $line, &$l, &$g)
{
	$log_id="";
	for($i=0;$i<10;$i++)
	{
		$log_id .= randLetter();
	}
	ob_start();
	var_dump($actionObj);
	$dumpObj = ob_get_clean();
	$logtext = " LOG ID: $log_id\r\nERRORE: $errtext\r\nDUMP DEI DATI:\r\n$dumpObj\r\nLINE:";
	WriteLog($logtext, $line);
	SendAdmin("[" . $line . "] Nuovo Log $log_id - $errtext", $l, $g);
}

function SendAdmin($t, &$l, &$g)
{
	$sendMessage = new SendMessage();
	$sendMessage->chat_id = ADMIN_CHAT_ID;
	$sendMessage->text = $t;
	$sendMessage->parse_mode = "Markdown";
	$promise = $g->performApiRequest($sendMessage);
	$promise->then(
		function (){

		},
		function (\Exception $exception) use ($l, $g){
			//LOGGARE
		}
	);
	$l->run();
}

function richiestaAPI($url)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_POST, 0);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($curl);
    curl_close($curl);
 
    return $result;
}

function WriteLog($body, $line)
{
	return file_put_contents("error.log", "(".date("d/m/Y H:i:s")."): ".$body." [".$line."]\r\n-----------------\r\n\r\n",FILE_APPEND);
}

function EditCart ($chat_id, $message_id, $url, $caption, $callbackdata)
{
	$boturl = "https://api.telegram.org/bot".TELEGRAM_BOT_KEY."/editMessageMedia";
	$bodyobj = ["chat_id" => $chat_id, "message_id" => $message_id, "media" => ["type" => "photo", "media" => $url, "caption" => $caption], "reply_markup" => ["inline_keyboard" => [[["text"=>"Aggiorna 🔄", "callback_data" => $callbackdata]]]]];

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL,            $boturl );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_POST,           1 );
	curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($bodyobj) ); 
	curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json')); 

	$result=curl_exec ($ch);
	return $result;
}

function GetChatMember ($user_id, $chat_id)
{
	$boturl = "https://api.telegram.org/bot".TELEGRAM_BOT_KEY."/getChatMember";
	$bodyobj = ["chat_id" => $chat_id, "user_id" => $user_id];
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL,            $boturl );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_POST,           1 );
	curl_setopt($ch, CURLOPT_POSTFIELDS,     $bodyobj); 
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:multipart/form-data']);

	$result=curl_exec ($ch);
	$resobj = json_decode($result, true);
	if(isset($resobj['ok']))
	{
		if($resobj['ok'])
		{
			if($resobj['result']['status'] == 'creator' || $resobj['result']['status'] == 'administrator' || $resobj['result']['status'] == 'member')
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}
	else
	{
		return false;
	}
}

function CheckAdminChat($chat_id)
{
	$boturl = "https://api.telegram.org/bot".TELEGRAM_BOT_KEY."/getChatMember";
	$bodyobj = ["chat_id" => $chat_id, "user_id" => TELEGRAM_BOT_ID];
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL,            $boturl );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_POST,           1 );
	curl_setopt($ch, CURLOPT_POSTFIELDS,     $bodyobj); 
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:multipart/form-data']);

	$result=curl_exec ($ch);
	$resobj = json_decode($result, true);
	if(isset($resobj['ok']))
	{
		if($resobj['ok'])
		{
			if($resobj['result']['status'] == 'administrator')
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}
	else
	{
		return false;
	}
}

function cmpbfiles($a, $b)
{
	$aobj = json_decode(file_get_contents($a), true);
	$bobj = json_decode(file_get_contents($b), true);
	return ($aobj['created'] < $bobj['created'] ? -1 : ($aobj['created'] > $bobj['created'] ? 1 : 0));
}

function HadWon($tblid, $lobby, $username)
{
	$res = false;
	$infotbl = json_decode(file_get_contents("infotbl_".$tblid.".txt"), true);
	$winners = $infotbl['lobbies'][$lobby]['vincitori'];
	foreach($winners as $prz => $winun)
	{
		if($winun == $username)
		{
			$res = true;
			break;		
		}
	}
	return $res;
}

function lock(){
    global $lock;
    try{
        $lock->acquire();
        return true;
    }
    catch(\Exception $ex)
    {
        return false;
    }
}

function unlock(){
    global $lock;
    $lock->release();
}
?>
