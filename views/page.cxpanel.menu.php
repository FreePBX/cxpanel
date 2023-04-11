<?php
if($checkStatus)
{
	header('Location: ' . $redirectUrl);
	die;
}
?>
<div class="container-fluid">
	<div class = "display full-border">
        <div class="row">
            <div class="col-sm-12">
				<br>
				<div class="alert alert-danger" role="alert">
					<p class="alert-offline">
						<i class="fa fa-exclamation-triangle" aria-hidden="true"></i> <?php echo _("Offline system!"); ?>
					</p>
					<p>
						<?php 
							if (!empty($infoStatus['error'])) 
							{
								echo $infoStatus['error'];
							}
							else
							{
								echo sprintf(_("The system is not accessible at this time, an error occurred %s."), $infoStatus['info']['http_code']);
							}
						?>
					</p>
					<br>
					<p>
						<a href="#" class="btn btn-success " onclick="location.reload();"><?php echo _("Click here to try again"); ?></a>
					</p>
				</div>
            </div>
        </div>
	</div>
</div>