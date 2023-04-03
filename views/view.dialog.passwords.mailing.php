<?php
$new_row = '
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-6">%s</div>
						<div class="col-md-6">%s</div>
					</div>
				</div>
			</div>
		</div>
	</div>
';
$solpan_row = '
	<div class="element-container">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-12">%s</div>
					</div>
				</div>
			</div>
		</div>
	</div>
';
?>
<div class="fpbx-container">
	<div class="display no-border">
		<?php
			echo sprintf($solpan_row, "<b>"._("The following is a list of users that were not sent password emails")."</b>");
			echo sprintf($new_row, _("User")."<hr>", _("Reason")."<hr>");
			if ($data['error'] == 0) {
				echo sprintf($new_row, _("None"), _("None"));
				echo sprintf($solpan_row, "<b>"._("All emails were sent successfully.")."</b>");
			}
			else
			{
				foreach($data['data'] as $item)
				{
					if (! $item['status'])
					{
						echo sprintf($new_row, $item['user_id'], $item['message']);
					}
				}
			}
		?>	
	</div>
</div>