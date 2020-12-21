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
use \unreal4u\TelegramAPI\Telegram\Types\InputMedia\Photo;
use \unreal4u\TelegramAPI\Telegram\Methods\SendMessage;
use \unreal4u\TelegramAPI\Telegram\Methods\EditMessageText;
use \unreal4u\TelegramAPI\Telegram\Methods\AnswerCallbackQuery;
use \unreal4u\TelegramAPI\Telegram\Methods\SendPhoto;
use \unreal4u\TelegramAPI\Telegram\Methods\DeleteMessage;
use \unreal4u\TelegramAPI\Telegram\Methods\EditMessageMedia;
use \unreal4u\TelegramAPI\Telegram\Types\Inline\Keyboard\ButtonInlineAnswer;
use \unreal4u\TelegramAPI\Telegram\Methods\GetChatMembersCount;

$tblnumbers = [1,2,3,4,5,6,7,8,9,10,
				11,12,13,14,15,16,17,18,19,20,
				21,22,23,24,25,26,27,28,29,30,
				31,32,33,34,35,36,37,38,39,40,
				41,42,43,44,45,46,47,48,49,50,
				51,52,53,54,55,56,57,58,59,60,
				61,62,63,64,65,66,67,68,69,70,
				71,72,73,74,75,76,77,78,79,80,
                81,82,83,84,85,86,87,88,89,90];
$loop = Factory::create();
$tgLog = new TgLog(TELEGRAM_BOT_KEY, new HttpClientRequestHandler($loop));

$crontime = (isset($argv[1])?$argv[1]:0);
if(true)
{
	$files = glob(dirname(__FILE__) . "/tbl*.txt");
	if(count($files) > 0)
	{
		$tblid = str_replace("tbl_","",basename($files[0],".txt"));
		$tblobj = json_decode(file_get_contents($files[0]), true);
		if(!file_exists("extract_".$tblid.".txt"))
		{
			if(isset($tblobj['ore']))
			{
				$curr = new DateTimeImmutable("now");
				$curr = $curr->setTimezone(new DateTimeZone('Europe/Rome'));
				$orenow = $curr->format("H");
				$minnow = $curr->format("i");
				$tmestr = explode(":",$tblobj['ore']);
				//echo $orenow.":".$minnow." - ".$tmestr[0].":".$tmestr[1].PHP_EOL;
				if($orenow == $tmestr[0] && $minnow == $tmestr[1])
				{
					$cartsfiles = glob("cart_".$tblid."_*.txt");
					$attive = 0;
					foreach($cartsfiles as $cf)
					{
						$cartobj = json_decode(file_get_contents($cf), true);
						$attive++;
					}
					if($attive > 1)
					{
						$numlobbies = ceil($attive/LOBBY_PARTS);
						$tblobj['lobbies'] = $numlobbies;

						$lobbypartsmin = floor($attive/$numlobbies);
						$lobbypartrest = $attive % $numlobbies;
						$infoobj = [];
						for($i=0; $i < $numlobbies; $i++)
						{
							$infoobj['lobbies'][$i] = ["vincitori" => [], "prize" => 2, "finita" => false];
						}
						$infoobj["tblmsg"] = 0;
						$infoobj["lastmsg"] = 0;
						$contlobby = 0;
						$numlobby = 0;
						shuffle($cartsfiles);
						foreach($cartsfiles as $cf)
						{
							$cartobj = json_decode(file_get_contents($cf), true);
							$cartobj['lobby'] = $numlobby;
							file_put_contents($cf, json_encode($cartobj));
							$contlobby++;
							if($contlobby == ($numlobby<$lobbypartrest ? $lobbypartsmin + 1 : $lobbypartsmin))
							{
								$contlobby = 0;
								$numlobby++;
							}
						}
						$extobj = [];
						for($i=0;$i<$numlobbies;$i++)
						{
							$extobj['lobbies'][$i] = [];
						}
						file_put_contents("extract_".$tblid.".txt", json_encode($extobj));
						$sendPhoto = new SendPhoto();
						$sendPhoto->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
						$sendPhoto->reply_to_message_id = $tblobj['msgid'];
						$sendPhoto->photo = IMAGES_SCRIPT_BASE_URL."makepanel.php?gameid=".$tblid."&anticache=".RandomID();
						$sendPhoto->caption = "⏰ La tombola inizia tra 15 secondi!";
						$sendPhoto->parse_mode = "Markdown";
						$promise = $tgLog->performApiRequest($sendPhoto);
						$promise->then(
							function ($response) use ($tblid, $tblobj, $tgLog, $loop, $infoobj){
								$infoobj['tblmsg'] = $response->message_id;
								file_put_contents("infotbl_".$tblid.".txt", json_encode($infoobj));	
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
								$editMessageText->text = $tblobj['text']."\n\n_Partecipanti: ".number_format($parts,0,"",".")."\nCartelle attive: ".number_format($carts,0,"",".")."_\n\n*In corso*";
								$inlineKeyboard = new Markup();
								$inlineKeyboardButton = new Button();
								$inlineKeyboardButton->text = "Mostra le mie cartelle 🗂";
								$inlineKeyboardButton->url = "https://t.me/".TELEGRAM_BOT_USERNAME."?start=cart_$tblid";
								$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
								$editMessageText->reply_markup = $inlineKeyboard;
								$editMessageText->parse_mode = "Markdown";
								$promise = $tgLog->performApiRequest($editMessageText);
								$promise->then(
									function () use ($tgLog, $loop) {
										$sendMessage = new SendMessage();
										$sendMessage->chat_id = ADMIN_CHAT_ID;
										$sendMessage->text = "Tombola iniziata";
										$sendMessage->parse_mode = "Markdown";
										$promise = $tgLog->performApiRequest($sendMessage);
										$promise->then(
											function (){
											
											},
											function (\Exception $exception) use ($loop, $tgLog){
												error_log("[".__LINE__."] ".$exception->getMessage());
											}
										);
										$loop->run();
									},
									function (\Exception $exception){
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
					else
					{
						$editMessageText = new EditMessageText();
						$editMessageText->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
						$editMessageText->message_id = $tblobj['msgid'];
						$editMessageText->text = $tblobj['text']."\n\n*Annullata per mancanza di iscritti sufficienti*";
						$inlineKeyboard = new Markup();
						$editMessageText->reply_markup = $inlineKeyboard;
						$editMessageText->parse_mode = "Markdown";
						$promise = $tgLog->performApiRequest($editMessageText);
						$promise->then(
							function () {
							},
							function (\Exception $exception){
								error_log("[".__LINE__."] ".$exception->getMessage());
							}
						);
						$loop->run();
						unlink("tbl_".$tblid.".txt");
						$files = glob("pubbl_".$tblid."_*.txt");
						foreach($files as $uf)
						{
							unlink($uf);
						}
					}
				}
			}
		}
		else
		{
			$extracted = json_decode(file_get_contents("extract_".$tblid.".txt"), true);
			$infotbl = json_decode(file_get_contents("infotbl_".$tblid.".txt"), true);
			$sellobby = [];
			for($e=0;$e<count($extracted['lobbies']); $e++)
			{
				if(!$infotbl['lobbies'][$e]['finita'])
				{
					$lefted = [];
					$extlobby = $extracted['lobbies'][$e];
					foreach($tblnumbers as $tn)
					{
						if(!in_array($tn, $extlobby))
						{
							$lefted[] = $tn;
						}
					}
					$n = $lefted[random_int(0, count($lefted) - 1)];
					$extracted['lobbies'][$e][] = $n;
					$sellobby[$e] = $n;
				}
			}
			file_put_contents("extract_".$tblid.".txt", json_encode($extracted));
			$vinta = [];
			$vincitori = [];
			$vincids = [];
			$vintxt = [];
			$spunte = [];
			$lastnumber = [];
			$lastpeople = [];
			$alertuser = [];
			$partscount = [];
			for($e=0;$e<count($extracted['lobbies']); $e++)
			{
				$vinta[$e] = false;
				$vincitori[$e] = [];
				$vincids[$e] = [];
				$vintxt[$e] = "";
				$spunte[$e] = 0;
				$lastnumber[$e] = [];
				$lastpeople[$e] = 0;
				$partscount[$e] = 0;
			}
			$cartsfiles = glob("cart_".$tblid."_*.txt");
			foreach($cartsfiles as $cf)
			{
				$cartobj = json_decode(file_get_contents($cf), true);
				$cartlobby = $cartobj['lobby'];
				foreach($cartobj['cartelle'] as $cart)
				{
					switch($infotbl['lobbies'][$cartlobby]['prize'])
					{
						case 2:
						{
							$vintxt[$cartlobby] = "AMBO";
							foreach($cart as $row)
							{
								$matches = 0;
								for($i=0;$i<5;$i++)
								{
									if($n == $row[$i])
									{
										$spunte[$cartlobby]++;
									}
									if(in_array($row[$i], $extracted['lobbies'][$cartlobby]))
									{
										$matches++;
									}
								}
								if($matches == 2 && !in_array($cartobj['username'], $vincitori[$cartlobby]))
								{
									$vincitori[$cartlobby][] = $cartobj['username'];
									$vincids[$cartlobby][] = $cartobj['userid'];
									$vinta[$cartlobby] = true;
								}
							}
							break;
						}
						case 5:
						{
							if(ALLOW_MULTIPLE_WINNINGS || !HasOlderWin($infotbl['lobbies'][$cartlobby]['vincitori'], $cartobj['username']))
							{
								$vintxt[$cartlobby] = "CINQUINA";
								foreach($cart as $row)
								{
									$matches = 0;
									for($i=0;$i<5;$i++)
									{
										if($n == $row[$i])
										{
											$spunte[$cartlobby]++;
										}
										if(in_array($row[$i], $extracted['lobbies'][$cartlobby]))
										{
											$matches++;
										}
									}
									if($matches == 5 && !in_array($cartobj['username'], $vincitori[$cartlobby]))
									{
										$vincitori[$cartlobby][] = $cartobj['username'];
										$vincids[$cartlobby][] = $cartobj['userid'];
										$vinta[$cartlobby] = true;
										if(in_array($cartobj['userid'], $lastnumber[$cartlobby]))
											unset($lastnumber[$cartlobby][$cartobj['userid']]);
									}
									else if($matches == 4)
									{
										if(!file_exists('alert_'.$tblid.'_'.$cartobj['userid'].'_'.$infotbl['lobbies'][$cartlobby]['prize'].'.txt'))
										{
											file_put_contents('alert_'.$tblid.'_'.$cartobj['userid'].'_'.$infotbl['lobbies'][$cartlobby]['prize'].'.txt', time());
											//AlertNearUser($cartobj['userid'], "CINQUINA", $tgLog, $loop);
											$alertuser[] = ["userid" => $cartobj['userid'], "prize" => "CINQUINA", "lobby" => $cartlobby];
										}
										if(!in_array($cartobj['username'], $vincitori[$cartlobby]))
										{
											$lastnumber[$cartlobby][$cartobj['userid']] = $cartobj['username'];
										}
									}
								}
							}
							break;
						}
						case 10:
						{
							if(ALLOW_MULTIPLE_WINNINGS || !HasOlderWin($infotbl['lobbies'][$cartlobby]['vincitori'], $cartobj['username']))
							{
								$vintxt[$cartlobby] = "DECINA";
								$mtchrows = 0;
								$mtnearrows = 0;
								foreach($cart as $row)
								{
									$matches = 0;
									for($i=0;$i<5;$i++)
									{
										if($n == $row[$i])
										{
											$spunte[$cartlobby]++;
										}
										if(in_array($row[$i], $extracted['lobbies'][$cartlobby]))
										{
											$matches++;
										}
									}
									if($matches == 5 && !in_array($cartobj['username'], $vincitori[$cartlobby]))
									{
										$mtchrows++;
									}
									else if($matches == 4)
									{
										$mtnearrows++;
									}
								}
								if($mtchrows == 2)
								{
									$vincitori[$cartlobby][] = $cartobj['username'];
									$vincids[$cartlobby][] = $cartobj['userid'];
									$vinta[$cartlobby] = true;
									if(in_array($cartobj['userid'], $lastnumber[$cartlobby]))
										unset($lastnumber[$cartlobby][$cartobj['userid']]);
								}
								else if($mtchrows == 1 && $mtnearrows >= 1)
								{
									if(!file_exists('alert_'.$tblid.'_'.$cartobj['userid'].'_'.$infotbl['lobbies'][$cartlobby]['prize'].'.txt'))
									{
										file_put_contents('alert_'.$tblid.'_'.$cartobj['userid'].'_'.$infotbl['lobbies'][$cartlobby]['prize'].'.txt', time());
										//AlertNearUser($cartobj['userid'], "DECINA", $tgLog, $loop);
										$alertuser[] = ["userid" => $cartobj['userid'], "prize" => "DECINA", "lobby" => $cartlobby];
									}
									if(!in_array($cartobj['username'], $vincitori[$cartlobby]))
									{
										$lastnumber[$cartlobby][$cartobj['userid']] = $cartobj['username'];
									}
								}
							}
							break;
						}
						case 15:
						{
							if(ALLOW_MULTIPLE_WINNINGS || !HasOlderWin($infotbl['lobbies'][$cartlobby]['vincitori'], $cartobj['username']))
							{
								$vintxt[$cartlobby] = "TOMBOLA";
								$matches = 0;
								foreach($cart as $row)
								{							
									for($i=0;$i<5;$i++)
									{
										if($n == $row[$i])
										{
											$spunte[$cartlobby]++;
										}
										if(in_array($row[$i], $extracted['lobbies'][$cartlobby]))
										{
											$matches++;
										}
									}
								}
								if($matches == 15 && !in_array($cartobj['username'], $vincitori[$cartlobby]))
								{
									$vincitori[$cartlobby][] = $cartobj['username'];
									$vincids[$cartlobby][] = $cartobj['userid'];
									$vinta[$cartlobby] = true;
									if(in_array($cartobj['userid'], $lastnumber[$cartlobby]))
										unset($lastnumber[$cartlobby][$cartobj['userid']]);
								}
								else if($matches == 14)
								{
									if(!file_exists('alert_'.$tblid.'_'.$cartobj['userid'].'_'.$infotbl['lobbies'][$cartlobby]['prize'].'.txt'))
									{
										file_put_contents('alert_'.$tblid.'_'.$cartobj['userid'].'_'.$infotbl['lobbies'][$cartlobby]['prize'].'.txt', time());
										//AlertNearUser($cartobj['userid'], "TOMBOLA", $tgLog, $loop);
										$alertuser[] = ["userid" => $cartobj['userid'], "prize" => "TOMBOLA", "lobby" => $cartlobby];
									}
									if(!in_array($cartobj['username'], $vincitori[$cartlobby]))
									{
										$lastnumber[$cartlobby][$cartobj['userid']] = $cartobj['username'];
									}
								}
							}
							break;
						}
					}
				}
				$partscount[$cartlobby] += 1;
			}
			$finaltxt = "";
			//print_r($lastnumber);
			for($e=0;$e<count($extracted['lobbies']); $e++)
			{
				$texttosend = "<b>Lobby ".($e+1)." (".$partscount[$e]." giocatori):\n</b>";
				if(!$infotbl['lobbies'][$e]['finita'])
				{
					$lastpeople = count($lastnumber[$e]);
					$texttosend .= "🎁 Premio attuale: <i>".($infotbl['lobbies'][$e]['prize'] == 2 ? "Ambo" :($infotbl['lobbies'][$e]['prize'] == 5 ? "Cinquina" : ($infotbl['lobbies'][$e]['prize'] == 10 ? "Decina" : ($infotbl['lobbies'][$e]['prize'] == 15? "Tombola" : "Conclusa"))))."</i>\n";
					$texttosend .= "🗳 Numero estratto: <b>"."<u>".$sellobby[$e]."</u>"."</b>\n📝 Presente in <b>".$spunte[$e]."</b> cartell".($spunte[$e]==1 ? "a" : "e").(!$vinta[$e] && $lastpeople>0?"\n👀 A <b>".$lastpeople."</b> person".($lastpeople==1?"a":"e")." manca un solo numero!":"");
					if($vinta[$e])
					{
						if(count($vincitori[$e]) > 1)
						{
							$numwin = count($vincitori[$e]);
							$vinfinidx = random_int(0, count($vincitori[$e]) - 1);
							$vinfinid = $vincids[$e][$vinfinidx];
							$vinfinun = $vincitori[$e][$vinfinidx];
							$vincitori[$e] = [];
							$vincids[$e] = [];
							$vincitori[$e][0] = $vinfinun;
							$vincids[$e][0] = $vinfinid;
							$texttosend .= "\n\n📣 @".$vincitori[$e][0]." grida <b>".$vintxt[$e]." (a sorte tra $numwin vincitori)</b>!";
						}
						else
						{
							$texttosend .= "\n\n📣 @".$vincitori[$e][0]." grida <b>".$vintxt[$e]."</b>!";
						}
						$sendMessage = new SendMessage();
						$sendMessage->chat_id = ($vincids[$e][0] < 0 ? ADMIN_CHAT_ID : $vincids[$e][0]);
						$sendMessage->text = ($vincids[$e][0] < 0 ? $vincids[$e][0]." - " : "")."🎉 Hai fatto *".$vintxt[$e]."!*";
						$sendMessage->parse_mode = "Markdown";
						$promise = $tgLog->performApiRequest($sendMessage);
						$promise->then(
							function (){
							
							},
							function (\Exception $exception) use ($loop, $tgLog){
								error_log("[".__LINE__."] ".$exception->getMessage());
							}
						);
						$loop->run();
						$infotbl['lobbies'][$e]['vincitori'][$infotbl['lobbies'][$e]['prize']] = $vincitori[$e][0];
						if($infotbl['lobbies'][$e]['prize'] < 15)
						{
							$infotbl['lobbies'][$e]['prize'] = ($infotbl['lobbies'][$e]['prize'] == 2 ? 5 :($infotbl['lobbies'][$e]['prize'] == 5 ? 10 : ($infotbl['lobbies'][$e]['prize'] == 10 ? 15 : 0)));
						}
						else
						{
							$infotbl['lobbies'][$e]['prize'] = 0;
							$infotbl['lobbies'][$e]['finita'] = true;
						}
					}
				}
				else
				{
					$texttosend .= "🎊 <i>TOMBOLA CONCLUSA! (".count($extracted['lobbies'][$e])." numeri estratti)</i>\nA fine partita verranno dichiarati i vincitori.";
				}
				$texttosend .= "\n\n\n";
				$finaltxt .= $texttosend;
			}
			foreach($alertuser as $au)
			{
				if(!$vinta[$au['lobby']])
				{
					AlertNearUser($au['userid'], $au['prize'], $tgLog, $loop);
				}
			}
			if($infotbl['lastmsg']>0)
			{
				$allfinish = true;
				foreach($infotbl['lobbies'] as $il)
				{
					if(!$il['finita'])
					{
						$allfinish = false;
						break;
					}
				}
				$almenounavinta = false;
				foreach($vinta as $v)
				{
					if($v)
					{
						$almenounavinta = true;
						break;
					}
				}
				$editMessageText = new EditMessageText();
				$editMessageText->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
				$editMessageText->message_id = $infotbl['lastmsg'];
				$editMessageText->text = $finaltxt;
				$editMessageText->parse_mode = "HTML";
				$promise = $tgLog->performApiRequest($editMessageText);
				$promise->then(
					function () use ($tblid, $infotbl, $tgLog, $loop, $extracted, $allfinish){
						$caption = "🗳 Numeri estratti: ".ContaMaxEstratti($extracted);
						$resedit = EditTbl((TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME), $infotbl['tblmsg'], IMAGES_SCRIPT_BASE_URL."makepanel.php?gameid=".$tblid."&anticache=".RandomID(), $caption);
						if($allfinish)
						{
							foreach(CAN_START_GAME as $f)
							{
								SendEndMessage($f, $infotbl, $tgLog, $loop);
							}
							unlink("tbl_".$tblid.".txt");
							$files = glob("alert_".$tblid."_*.txt");
							foreach($files as $uf)
							{
								unlink($uf);
							}
							$files = glob("pubbl_".$tblid."_*.txt");
							foreach($files as $uf)
							{
								unlink($uf);
							}
						}
						file_put_contents("infotbl_".$tblid.".txt", json_encode($infotbl));
					},
					function (\Exception $exception){
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
			else
			{
				$sendMessage = new SendMessage();
				$sendMessage->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
				$sendMessage->text = $finaltxt;
				$sendMessage->parse_mode = "HTML";
				$promise = $tgLog->performApiRequest($sendMessage);
				$promise->then(
					function ($response) use ($tblid, $infotbl, $tgLog, $loop, $allfinish, $extracted){
						$infotbl['lastmsg'] = $response->message_id;
						$caption = "🗳 Numeri estratti: ".ContaMaxEstratti($extracted);
						$resedit = EditTbl((TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME), $infotbl['tblmsg'], IMAGES_SCRIPT_BASE_URL."makepanel.php?gameid=".$tblid."&anticache=".RandomID(), $caption);
						//error_log(print_r($resedit));
						if($allfinish)
						{
							foreach(CAN_START_GAME as $f)
							{
								SendEndMessage($f, $infotbl, $tgLog, $loop);
							}
							unlink("tbl_".$tblid.".txt");
							$files = glob("alert_".$tblid."_*.txt");
							foreach($files as $uf)
							{
								unlink($uf);
							}
							$files = glob("pubbl_".$tblid."_*.txt");
							foreach($files as $uf)
							{
								unlink($uf);
							}
						}
						file_put_contents("infotbl_".$tblid.".txt", json_encode($infotbl));
					},
					function (\Exception $exception) use ($loop, $tgLog){
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
			}
			if($allfinish)
			{
				$infotbl = json_decode(file_get_contents("infotbl_".$tblid.".txt"), true);
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
				$editMessageText->text = $tblobj['text']."\n\n_Partecipanti: ".number_format($parts,0,"",".")."\nCartelle attive: ".number_format($carts,0,"",".")."_\n\n*Conclusa*";
				$inlineKeyboard = new Markup();
				$editMessageText->reply_markup = $inlineKeyboard;
				$editMessageText->parse_mode = "Markdown";
				$promise = $tgLog->performApiRequest($editMessageText);
				$promise->then(
					function () use ($tgLog, $loop) {
					},
					function (\Exception $exception){
						error_log("[".__LINE__."] ".$exception->getMessage());
					}
				);
				$loop->run();
				$endtxt = "🎊 <i>TOMBOLA CONCLUSA!</i>\n<b>I vincitori sono:</b>\n";
				for($e = 0; $e < count($infotbl['lobbies']); $e++)
				{
					$endtxt .= "<b>Lobby ".($e+1)." (".$partscount[$e]." giocatori):</b>\n";
					foreach($infotbl['lobbies'][$e]['vincitori'] as $vk => $va)
					{
						$endtxt .= "<b>".($vk == 2 ? "🎗 Ambo: " :($vk == 5 ? "🥉 Cinquina: " : ($vk == 10 ? "🥈 Decina: " : ($vk == 15 ? "🥇 Tombola: " : ""))))."</b>";
						$endtxt .= "@".$va."\n";					
					}
					$endtxt .= "\n";
				}
				$endtxt .= "\n<b>Congratulazioni ai vincitori.</b>\n<i>Grazie a tutti per aver giocato</i>";
				sleep(10);
				$deleteMessage = new DeleteMessage();
				$deleteMessage->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
				$deleteMessage->message_id = $infotbl['lastmsg'];
				$promise = $tgLog->performApiRequest($deleteMessage);
				$promise->then(
					function () use ($tgLog, $loop, $endtxt) {
						sleep(1);
						$sendMessage = new SendMessage();
						$sendMessage->chat_id = (TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME);
						$sendMessage->text = $endtxt;
						$sendMessage->parse_mode = "HTML";
						$promise = $tgLog->performApiRequest($sendMessage);
						$promise->then(
							function ($response) {
							},
							function (\Exception $exception) use ($loop, $tgLog){
								error_log("[".__LINE__."] ".$exception->getMessage());
							}
						);
						$loop->run();
					},
					function (\Exception $exception) use ($deleteMessage) {
						error_log("[".__LINE__."] ".$exception->getMessage(). " - ". print_r($deleteMessage, true));
					}
				);
				$loop->run();
			}
		}
	}
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

function EditTbl ($chat_id, $message_id, $url, $caption)
{
	$boturl = "https://api.telegram.org/bot".TELEGRAM_BOT_KEY."/editMessageMedia";
	$bodyobj = ["chat_id" => $chat_id, "message_id" => $message_id, "media" => ["type" => "photo", "media" => $url, "caption" => $caption, "parse_mode" => "Markdown"]];
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL,            $boturl );
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($ch, CURLOPT_POST,           1 );
	curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($bodyobj) ); 
	curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: application/json')); 

	$result=curl_exec ($ch);
	//echo $result;
	return $result;
}

function ContaMaxEstratti($extr)
{
	$maxext = 0;
	foreach($extr['lobbies'] as $el)
	{
		if(count($el) > $maxext)
		{
			$maxext = count($el);
		}
	}
	return $maxext;
}

function SendEndMessage($chat_id, $infotbl, &$tgLog, &$loop, $numtry = 0)
{
	$vinctxt = "";
	for($l=0;$l<count($infotbl['lobbies']); $l++)
	{
		$vinctxt .= "Lobby ".($l+1).":\n";
		$vincitori = $infotbl['lobbies'][$l]['vincitori'];
		foreach($vincitori as $vp => $vi)
		{
			$premiotxt = '';
			switch($vp)
			{
				case 2:
				{
					$premiotxt = "AMBO";
					break;
				}
				case 5:
				{
					$premiotxt = "CINQUINA";
					break;
				}
				case 10:
				{
					$premiotxt = "DECINA";
					break;
				}
				case 15:
				{
					$premiotxt = "TOMBOLA";
					break;
				}
			}
			$vinctxt .= "- ".$premiotxt.": @".$vi."\n";
		}
		$vinctxt .= "\n";
	}
	$sendMessage = new SendMessage();
	$sendMessage->chat_id = $chat_id;
	$sendMessage->text = "🎊 <i>TOMBOLA CONCLUSA!</i>\n\nVincitori:\n".$vinctxt;
	$inlineKeyboard = new Markup();
	$inlineKeyboardButton = new Button();
	$inlineKeyboardButton->text = "Vai al canale 📣";
	$inlineKeyboardButton->url = "https://t.me/".str_replace("@","",(TOMBOLA_TESTING ? TEST_CHANNEL_NAME : CHANNEL_NAME));
	$inlineKeyboard->inline_keyboard[][] = $inlineKeyboardButton;
	$sendMessage->reply_markup = $inlineKeyboard;
	$sendMessage->parse_mode = "HTML";
	$promise = $tgLog->performApiRequest($sendMessage);
	$promise->then(
		function (){
	
		},
		function (\Exception $exception) use ($chat_id, $vincitori, $tgLog, $loop, $numtry){
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
					SendEndMessage($chat_id, $vincitori, $tgLog, $loop, $newnumtry);
				}
				else
				{
					error_log("error $chat_id - ".__LINE__." - ".$exception.getMessage() );
				}
			}
			else
			{
				error_log("error $chat_id - ".__LINE__." - ".$exception.getMessage(). " - MAX TRY EXCEEDED" );
			}			
		}
	);
	$loop->run();
}

function AlertNearUser($chat_id, $vincita, &$tgLog, &$loop, $numtry = 0)
{
	$sendMessage = new SendMessage();
	$sendMessage->chat_id = ($chat_id < 0 ? ADMIN_CHAT_ID : $chat_id);
	$sendMessage->text = ($chat_id < 0 ? $chat_id." - " : "")."👀 Ti manca solo un numero per fare ".$vincita."!\n\nControlla le tue cartelle con il comando /cartelle";
	$sendMessage->parse_mode = "Markdown";
	$promise = $tgLog->performApiRequest($sendMessage);
	$promise->then(
		function (){
	
		},
		function (\Exception $exception) use ($chat_id, $tgLog, $loop, $numtry, $vincita){
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
					AlertNearUser($chat_id, $vincita, $tgLog, $loop, $newnumtry);
				}
				else
				{
					error_log("error $chat_id - ".__LINE__." - ".$exception.getMessage() );
				}
			}
			else
			{
				error_log("error $chat_id - ".__LINE__." - ".$exception.getMessage(). " - MAX TRY EXCEEDED" );
			}			
		}
	);
	$loop->run();
}

function HasOlderWin($oldwins, $actun)
{
	$res = false;
	foreach($oldwins as $prz => $winun)
	{
		if($winun == $actun)
		{
			$res = true;
			break;		
		}
	}
	return $res;
}
?>