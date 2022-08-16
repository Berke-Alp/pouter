<?php

// You can access view data by $_v array.

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Page</title>
</head>
<body>
    
    <h1>Some error occured</h1>

    <p>Here is the error status code: <?=$_v['status_code']?></p>
    <span>And the other view data: <?=$_v['other_data']?></span>

</body>
</html>