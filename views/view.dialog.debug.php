<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Main Log")); ?><br>
				<?php echo sprintf('<textarea rows="10" cols="90">%s</textarea>', $debug['main_log']['read']); ?><br>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Modify Log")); ?><br>
				<?php echo sprintf('<textarea rows="10" cols="90">%s</textarea>', $debug['modify_log']['read']); ?><br>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Server")); ?><br>
				<?php var_dump($debug['server']); ?>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Email")); ?><br>
				<?php var_dump($debug['email']); ?>
				<!-- //TODO: Get value in textaread -->
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Voicemail Agent")); ?><br>
				<?php var_dump($debug['voicemail_agent']); ?>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Recording Agent")); ?><br>
				<?php var_dump($debug['recording_agent']); ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Conference Rooms")); ?><br>
				<?php var_dump($debug['rooms']); ?>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Queues")); ?><br>
				<?php var_dump($debug['queues']); ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<div class="row">
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Users")); ?><br>
				<?php var_dump($debug['users']); ?>
			</div>
			<div class="col-md-6">
				<?php echo sprintf('<b>%s</b>', _("Managed Items")); ?><br>
				<?php var_dump($debug['managed_items']); ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-12">
		<?php echo sprintf('<b>%s</b>', _("Phone Numbers")); ?><br>
		<?php var_dump($debug['phone_numbers']); ?>
	</div>
</div>