<?php
	$conn = mysqli_connect('localhost', 'club532583_dmfirst', 'club532583_dmfirst', 'club532583_dmfirst');
	
	if (!$conn) {
		echo "Error: " . mysqli_connect_error();
		exit();
	}
	
	date_default_timezone_set("Asia/Kolkata"); 
?>