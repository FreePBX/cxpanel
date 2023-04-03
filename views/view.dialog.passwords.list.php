<?php
	$info = sprintf('These are the initail passwords that have been created for the %s users during the installation of the module.</br> Extensions that were created after installation of the module or have had their password changed from the inital value will not show up in the list.', $brandName);
?>

<div class="alert alert-info" role="alert"><?php echo $info; ?></div>
<table class="table table-striped table-bordered">
	<thead>
		<th><?php echo _("UserID"); ?></th>
		<th><?php echo _("Password"); ?></th>
	</thead>
	<tbody>
		<?php
		foreach ($list as $row)
		{
			echo sprintf("<tr><td>%s</td><td>%s</td></tr>", $row['user_id'], $row['initial_password']);
		}
		?>
	</tbody>
</table>
	






		
		
		
		