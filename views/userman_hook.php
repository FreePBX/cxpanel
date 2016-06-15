<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="cxpanel_add_user"><?php echo sprintf(_("Add To %s"),$cxpanelBrandName)?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="cxpanel_add_user"></i>
					</div>
					<div class="col-md-9">
						<span class="radioset">
							<input type="radio" id="cxpanel_add_user_yes" name="cxpanel_add_user" value="1" <?php echo ($addUser) ? 'checked' : ''?>>
							<label for="cxpanel_add_user_yes"><?php echo _('Yes')?></label>
							<input type="radio" id="cxpanel_add_user_no" name="cxpanel_add_user" value="0" <?php echo (!is_null($addUser) && !$addUser) ? 'checked' : ''?>>
							<label for="cxpanel_add_user_no"><?php echo _('No')?></label>
							<?php if($mode == "user") {?>
								<input type="radio" id="cxpanel_add_user_inherit" name="cxpanel_add_user" value='inherit' <?php echo is_null($addUser) ? 'checked' : ''; ?>>
								<label for="cxpanel_add_user_inherit"><?php echo _('Inherit')?></label>
							<?php } ?>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="cxpanel_add_user-help" class="help-block fpbx-help-block"><?php echo _("Makes this user available in $cxpanelBrandName.")?></span>
		</div>
	</div>
</div>
