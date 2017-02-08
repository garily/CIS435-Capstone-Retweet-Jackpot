<!DOCTYPE html>
<!-- CIS 435 Capstone Project -- By Ruoyu Li -->
<html>
	<head>
		<title>Retweet Checker &amp; Lucky Draw</title>
		<link rel="stylesheet" href="style/styles.css">
		<script type="text/javascript">
			function draw(){
				document.getElementById("winner").style.display = "";
			}
			function hide(){
				document.getElementById("winner").style.display = "none";
				document.getElementById("record").style.display = "none";
			}
		</script>
	</head>
	<body>
		<h1>Retweet Prize</h1>
		<div id="formInput">
			<form action="?" method="post">
				<label for="tweetID">The ID of the Tweet you want to lookup:</label>
				<br/>
				<input type="text" id="tweetID" name="tweetID" pattern="\d{18}" placeholder="18-digit TweetID" required/>
				<br/><br/>
				<label for="originalTweet">The text of the Tweet you want to lookup:</label>
				<br/>
				<textarea type="text" id="originalTweet" name="originalTweet" placeholder="Tweet content (text only)" required/></textarea>
				<br/><br/>
				<input class="btn" type="submit" name="submit" value="Submit" onclick=hide()/>
				<br/><br/>
			</form>
		</div>
		<div id="result">
			<table class="center">
			<?php
			$host = '';
    		$username = '';
    		$password = '';
    		$db = 'CIS435_Capstone';
    		$conn = mysqli_connect($host, $username, $password);
			$status = mysqli_select_db($conn, $db);
			$i; $j; $response;
			
			$list = array();
			if(isset($_POST['submit'])){
				require 'token.php';
				require 'tmhOAuth.php';
				if (isset($_POST['tweetID'])) $tweetID = htmlspecialchars($_POST['tweetID']);
				if (isset($_POST['originalTweet'])) $originalTweet = htmlspecialchars($_POST['originalTweet']);
				
				if (!$conn) {
					print 'Database connection failure!<br/>';
				}
				else {
					$sql = "SELECT * FROM Records WHERE `tweetID`=$tweetID;";
					$result = mysqli_query($conn, $sql);
					$sqlArr = mysqli_fetch_row($result);
					if($sqlArr[0]!= null ){//if record exists in database
						$string = 'There is already a record for prizing with this tweet, and the winner is :'.
						'<br/><a href="https://twitter.com/intent/user?user_id='.
						$sqlArr[1].'" target="_blank">@'.
						$sqlArr[2].
						'</a><br/>(User ID: '.
						$sqlArr[1].
						')<br/>';
						echo '<h2 id="record">'.$string.'</h2>';
					}
					else {//if no record in database
									
				if (!empty($tweetID) && !empty($originalTweet)) {
				$connection = new tmhOAuth(array(
    				'consumer_key' => $consumer_key,
    				'consumer_secret' => $consumer_secret,
    				'user_token' => $user_token,
    				'user_secret' => $user_secret
				));
				
				$http_code = $connection->request('GET', $connection->url('1.1/statuses/retweets/'.$tweetID.'.json'),
					array('count' => 100, 'trim_user' => 'true'));
				if ($http_code == 200) {
					echo '<tr class="center">
					<th style="min-width: 80px;">#</th>
					<th style="min-width: 250px">TweetID</th>
					<th style="min-width: 250px">Retweeter</th>
					</tr>';
    				$response = json_decode($connection->response['response'], true);
					for($i = 0; $i<count($response);$i++){
						print_r('<tr class="center"><td>'.$i.'</td><td>'.$response[$i]['id_str'].'</td>');
						print_r('<td>'.$response[$i]['user']['id_str'].'</td></tr>');
						array_push($list, $response[$i]['user']['id_str']);
					}
				}
				else {
    				if ($http_code == 429) {
        				print 'Error: Twitter API rate limit reached';
    				}
    				else {
        				print 'Error: Twitter was not able to process that request: '.$connection->response['response'];
    				}
				}
				
				$max_id = $response[$i - 1]['id_str'];
				$cache_max_id = $max_id;
				do {
					$http_code = $connection->request('GET',
    					$connection->url('1.1/search/tweets'),
    					array('q' => $originalTweet, 'result_type' => 'recent', 'count' => 100, 'max_id' => $max_id)
					);
					if ($http_code == 200) {
						$response = json_decode($connection->response['response'], true);
						for($j = 0; $j < count($response['statuses']) - 1; $j++){
							print_r('<tr><td class="center">'.($j + $i).'</td><td class="center">'.$response['statuses'][$j + 1]['id_str'].'</td>');
							print_r('<td class="center">'.$response['statuses'][$j + 1]['user']['id_str'].'</td></tr>');
							array_push($list, $response['statuses'][$j + 1]['user']['id_str']);
						}
						$i += $j;
						$cache_max_id = $max_id; //in case rate limit is reached
						if ($j != 0) $max_id = $response['statuses'][$j]['id_str'];
					}
					else {
    					if ($http_code == 429) {
	        				print 'Error: Twitter API rate limit reached, will retry and continue in 2 min';
							$max_id = $cache_max_id;
							$currenttime = time(); 
							$usercount = date('i:s', (2 * 60) - ($currenttime % (2 * 60))); 
							while ($usercount>0);
							continue;
    					}
    					else {
        					print 'Error: Twitter was not able to process that request: '.$connection->response['response'];
    					}
					}			
				}while($http_code == 200 && count($response['statuses']) > 1);
			}
			
			if(!empty($list)){
					if(shuffle($list)){
						$http_code = $connection->request('GET', $connection->url('1.1/users/show.json'),
							array('user_id' => $list[0], 'include_entities' => 'false'));
						if ($http_code == 200) {
							$response = json_decode($connection->response['response'], true);
							$string = 'This guy gets the lucky draw!<br/><a href="https://twitter.com/intent/user?user_id='.$list[0].'" target="_blank">@'.$response['name'].'</a><br/>(User ID: '. $list[0].')<br/>';
							$tmp = $response['name'];
							//mysqli_select_db($conn, $db);
							$sql = "INSERT INTO `Records` (`tweetID`, `winnerID`, `winnerName`) VALUES ('$tweetID', '$list[0]', '$tmp');";
							$result = mysqli_query($conn, $sql);
							if(!$result) {echo '<br/>Failure updating database! Record isn\'t saved!'; echo $msg = mysqli_error($conn);}
								else $string = $string.'<br/>Record added to database!';
						}
						else {
							if ($http_code == 429) {
	        					$string = 'Error: Twitter API rate limit reached, please retry later';
	        				}
							else {
	        					$string = 'Error: Twitter was not able to process that request: '.$connection->response['response'];
							}
						}
					}
					else {
	        			$string = "Shuffle failure!";
					}
				}
				else {
	        		$string = "Retweeter list empty!";
				}	
			}
		}
		}
		?>
		</table>
		</div>
		<br/>		
		<div id="pickWinner">
			<button id="btn2" type="button" onclick=draw()>Draw Winner!</button>
			<div class="center" id="winner" style = "display : none"><?php print $string;?></div>
		</div>
		<footer class="center">&copy; Gary Li -  2016</footer>
	</body>
</html>
