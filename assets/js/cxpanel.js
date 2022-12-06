
function SettingsSave(e)
{
	e.preventDefault();
	t = e.target || e.srcElement;
	
	var return_status = true;
	var return_msg 	  = "";
	var settingsForm  = document.getElementById('cxpanel_settings_form');
	
	let list_check = {
		'cxpanel_name'		   	: _('Error: Name cannot be blank.'),
		'cxpanel_api_host'	   	: _('Error: Server API host cannot be blank.'),
		'cxpanel_api_port' 	   	: _('Error: Server API port cannot be blank.'),
		'cxpanel_api_username' 	: _('Error: Server API username cannot be blank.'),
		'cxpanel_api_password' 	: _('Error: Server API password cannot be blank.'),

		'cxpanel_asterisk_host' : _('Error: Asterisk host cannot be blank.'),

		'cxpanel_client_port'   : _('Error: Client port cannot be blank.'),

		'cxpanel_voicemail_agent_identifier' 		 : _('Error: Voicemail agent identifier cannot be blank.'),
		'cxpanel_voicemail_agent_directory' 		 : _('Error: Voicemail agent directory cannot be blank.'),
		'cxpanel_voicemail_agent_resource_host' 	 : _('Error: Voicemail agent resource host cannot be blank.'),
		'cxpanel_voicemail_agent_resource_extension' : _('Error: Voicemail agent resource extension cannot be blank.'),
		
		'cxpanel_recording_agent_identifier' 		 : _('Error: Recording agent identifier cannot be blank.'),
		'cxpanel_recording_agent_directory' 		 : _('Error: Recording agent directory cannot be blank.'),
		'cxpanel_recording_agent_resource_host' 	 : _('Error: Recording agent resource host cannot be blank.'),
		'cxpanel_recording_agent_resource_extension' : _('Error: Recording agent resource extension cannot be blank.'),
		'cxpanel_recording_agent_filename_mask' 	 : _('Error: Recording agent filename mask cannot be blank.'),
	};

	
	Object.entries(list_check).every(entry => {
		const [key, value] = entry;
		let item = settingsForm.elements[key];
		if(item.value.length == 0) {
			return_msg = return_msg + value + "<br>";
			return_status = false;
		}
		return true;
	});

	if (return_status == true)
	{
		if(settingsForm.elements['cxpanel_api_port'].value != parseInt(settingsForm.elements['cxpanel_api_port'].value)) {
			return_msg = _('Server port must be numeric.');
			return_status = false;
		}
	}

	if (return_msg != "") {
		fpbxToast( return_msg, '', 'error');
	}

	if (return_status == true)
	{
		settingsForm.elements['cxpanel_email_body'].value = $('.cxpanel_email_body_editor').val();
		settingsForm.submit();
	}
}

function ActivateLicense(e)
{
	e.preventDefault();
	t = e.target || e.srcElement;

	var activateForm = document.getElementById('cxpanel_activate_license_form');				
	if(activateForm.elements['cxpanel_activate_serial_key'].value.length == 0)
	{
		fpbxToast(_('Error: Please specify a serial key.'), '', 'error');
	}
	else
	{
		activateForm.submit();
	}
}

function BindLicense(e)
{
	e.preventDefault();
	t = e.target || e.srcElement;

	var message_err  = "";
	var isCheckOk 	 = true;
	var activateForm = document.getElementById('cxpanel_bind_license_form');

	if(activateForm.elements['cxpanel_bind_license_to'].value.length == 0)
	{
		message_err += _('Error: Please specify the licensed to value.') + "<br>";
		isCheckOk = false;
	}
	if(activateForm.elements['cxpanel_bind_license_email'].value.length == 0)
	{
		message_err += _('Error: Please specify an email for the license.') + "<br>";
		isCheckOk = false;
	}

	if (message_err != "") 	{ fpbxToast(message_err, '', 'error'); }
	if (isCheckOk) 			{ activateForm.submit(); }
}
function BindLicenseCancel(e)
{
	e.preventDefault();
	t = e.target || e.srcElement;
	var activateForm = document.getElementById('cxpanel_bind_license_cancel_form');
	activateForm.submit();
}

function emailPasswords(e)
{
	e.preventDefault();0
	t = e.target || e.srcElement;
	
	fpbxConfirm(
		_('Email passwords to users?'),
		_("Yes"), _("No"),
		function() 
		{
			var wWidth 		= $(window).width();
			var dWidth 		= wWidth * 0.8;
			var wHeight 	= $(window).height();
			var dHeight 	= wHeight * 0.8;

			$('<div id="mailing-passwod-dialog" title="' + _('Mailing Passwords') + '">' + _('Loading...') + '<i class="fa fa-spinner fa-spin"></i></div>').dialog({
				autoOpen: true,
				height: dHeight,
				width: dWidth,
				resizable: false,
				modal: true,
				buttons: {
					Close: {
						text: _("Close"),
						click: function() {
							$(this).dialog( "close" );
							$(this).remove();
						}
					},
				},
				open: function() {
					$('html').attr('data-scrollTop', $(document).scrollTop()).css('overflow', 'hidden');
					$(this).dialog('option','position',{ my: 'center', at: 'center', of: window });

					var $this = this;
					var post_data = {
						module: "cxpanel",
						command: "sendPassword",
					};
					$.post( window.FreePBX.ajaxurl, post_data, function(data) {
						fpbxToast(data.message, '', (data.status ? 'success' : 'error') );
						if (data.status) {
							$($this).html(data.html);
						}
					})
					.fail(function(xhr, textStatus, errorThrown) {
						fpbxToast(xhr.responseText, '', "error");
					});
				},
				close: function() {
					$(this).dialog( "close" );
					$(this).remove();
				},
				beforeClose: function(event, ui) {
					//Fix recover scrollbar
					var scrollTop = $('html').css('overflow', 'auto').attr('data-scrollTop') || 0;
					if( scrollTop ) $('html').scrollTop( scrollTop ).attr('data-scrollTop','');
				}
			});
		}
	);
}

function showInitPasswords(e)
{
	e.preventDefault();
	t = e.target || e.srcElement;
	
	var wWidth 		= $(window).width();
	var dWidth 		= wWidth * 0.8;
	var wHeight 	= $(window).height();
	var dHeight 	= wHeight * 0.8;

	$('<div id="init-passwod-dialog" title="' + _('Initial User Passwords') + '">' + _('Loading...') + '<i class="fa fa-spinner fa-spin"></i></div>').dialog({
		autoOpen: true,
		height: dHeight,
		width: dWidth,
		resizable: false,
		modal: true,
		buttons: {
			Download: {
				text: _("Download CSV"),
				click: function() {
					export_passwords(e);
				}
			},
			Close: {
				text: _("Close"),
				click: function() {
					$(this).dialog( "close" );
					$(this).remove();
				}
			},
		},
		open: function() {
			$('html').attr('data-scrollTop', $(document).scrollTop()).css('overflow', 'hidden');
			$(this).dialog('option','position',{ my: 'center', at: 'center', of: window });

			var $this = this;
			var post_data = {
				module: "cxpanel",
				command: "initialUserPassword",
			};
			$.post( window.FreePBX.ajaxurl, post_data, function(data) {
				
				if (data.status) {
					$($this).html(data.html);
				}
				else
				{
					fpbxToast(data.message, '', 'error');
				}
			})
			.fail(function(xhr, textStatus, errorThrown) {
				fpbxToast(xhr.responseText, '', "error");
			});
		},
		close: function() {
			$(this).dialog( "close" );
			$(this).remove();
		},
		beforeClose: function(event, ui) {
			//Fix recover scrollbar
			var scrollTop = $('html').css('overflow', 'auto').attr('data-scrollTop') || 0;
			if( scrollTop ) $('html').scrollTop( scrollTop ).attr('data-scrollTop','');
		}
	});
}

function runDebug(e)
{
	e.preventDefault();
	t = e.target || e.srcElement;

	var wWidth 		= $(window).width();
	var dWidth 		= wWidth * 0.95;
	var wHeight 	= $(window).height();
	var dHeight 	= wHeight * 0.95;

	$('<div id="debug-dialog" title="' + _('Debug') + '">' + _('Loading...') + '<i class="fa fa-spinner fa-spin"></i></div>').dialog({
		autoOpen: true,
		height: dHeight,
		width: dWidth,
		resizable: true,
		modal: true,
		// buttons: {
		// 	Close: {
		// 		text: _("Close"),
		// 		click: function() {
		// 			$(this).dialog( "close" );
		// 			$(this).remove();
		// 		}
		// 	},
		// },
		open: function() {
			$('html').attr('data-scrollTop', $(document).scrollTop()).css('overflow', 'hidden');
			$(this).dialog('option','position',{ my: 'center', at: 'center', of: window });

			var $this = this;
			var post_data = {
				module: "cxpanel",
				command: "debug",
			};
			$.post( window.FreePBX.ajaxurl, post_data, function(data) {
				
				if (data.status) {
					$($this).html(data.html);
				}
				else
				{
					fpbxToast(data.message, '', 'error');
				}
			})
			.fail(function(xhr, textStatus, errorThrown) {
				fpbxToast(xhr.responseText, '', "error");
			});
		},
		close: function() {
			$(this).dialog( "close" );
			$(this).remove();
		},
		beforeClose: function(event, ui) {
			//Fix recover scrollbar
			var scrollTop = $('html').css('overflow', 'auto').attr('data-scrollTop') || 0;
			if( scrollTop ) $('html').scrollTop( scrollTop ).attr('data-scrollTop','');
		}
	});
}

function export_passwords(e)
{
	e.preventDefault();
	$url = window.FreePBX.ajaxurl + "?module=cxpanel&command=download_password_csv";
	window.location.assign($url);
}

function ShowHideAll(new_status = true)
{
	var isVal = "";
	if (new_status == true)
	{
		isVal = ":visible";
	}
	else
	{
		isVal = ":hidden";
	}
	$(".section-title" ).each(function() {
		var id = $(this).data("for"), icon = $(this).find("i.fa");
		if (icon.length > 0) {
			if ($(".section[data-id='" + id + "']").is(isVal)) {
				return;
			}
			icon.toggleClass("fa-minus").toggleClass("fa-plus");
			$(".section[data-id='" + id + "']").slideToggle("slow", function() {
				positionActionBar();
			});
		}
	});
}

function copyToClipboard(text) {
    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val(text).select();
    document.execCommand("copy");
    $temp.remove();
}

$(function()
{
	$('.btn-show-password').on("click", function(e) {
		showInitPasswords(e);
	});

	$('.btn-sendmail-password').on("click", function(e) {
		emailPasswords(e);
	});

	$('.btn-debug').on("click", function(e) {
		runDebug(e);
	});

	$(".btn-expand-all").click(function() {
		ShowHideAll(true);
	});

	$(".btn-collapse-all").click(function() {
		ShowHideAll(false);
	});

	$(".btn-activate-license").click(function(e) {
		ActivateLicense(e);
	});

	$(".btn-bind-license").click(function(e) {
		BindLicense(e);
	});

	$(".btn-bind-license-cancel").click(function(e) {
		BindLicenseCancel(e);
	});

	$(".btn-settings-save").click(function(e) {
		SettingsSave(e);
	});

	$('.cxpanel_email_body_editor').richText({
		maxlengthIncludeHTML: true,
	});

	$('.legend_tag').on("click", function(e) {
		e.preventDefault();
		t = e.target || e.srcElement;
		let code = $(t).text();
		
		copyToClipboard(code);
		fpbxToast(code + _(" copied to clipboard."), '', 'success' );
	});

	$('.btn-reset-value').on("click", function(e) {
		e.preventDefault();
		t = e.target || e.srcElement;
		var input = $(t).closest('.input-group').find('input');
		input.val($(t).val());
		fpbxToast(_("Value reset to its default value."), '', 'success' );
	});

});