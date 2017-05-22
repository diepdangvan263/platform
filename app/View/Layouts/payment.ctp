<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<?php
    if(!isset($title_for_app))
        $title_for_app = __('Nạp thẻ');

    echo "<title>" . $title_for_app . "</title>";
    echo "<meta name = 'title' content = '$title_for_app' />";
    echo '<meta name = "viewport" content = "user-scalable=no, initial-scale=1.0, maximum-scale=1.0, width=device-width" />';
    echo '<meta name="apple-mobile-web-app-capable" content="yes"/>';
?>
<!-- nocache -->
<?php
	echo $this->Html->css('/uncommon/payment/css/style.css');
	echo $this->Html->script('/uncommon/payment/js/less-1.3.3.min.js');
	echo $this->fetch('css');
	echo $this->fetch('script');
?>

</head>

<?php
echo $this->fetch('content');

if (Configure::read('debug') == 2){
//	echo $this->element('sql_dump');
}
?>
</html>