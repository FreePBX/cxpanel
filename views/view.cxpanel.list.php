<?php

function lineParse($line)
{
	$data_return = "";
	if (is_array($line) && !empty($line))
	{
		if (isset($line['raw']))
		{
			$data_return = $line['raw'];
		}
		else if(isset($line['name']))
		{
			$params_title = array(
				$line['name'],
				$line['title'],
				$line['name'],
			);
			$data_return = '
				<div class="section-title" data-for="%s">
					<h2><i class="fa fa-minus"></i>%s</h2>
				</div>
				<div class="section" data-id="%s">
			';
			$data_return = vsprintf($data_return, $params_title);
		}
		else if(isset($line['type']) && $line['type'] == 'endsec'){
			$data_return = '</div><br>';
		}
		else if (isset($line['colspan']) && $line['colspan'] == true)
		{
			$skip = false;
			if (isset($line['colspan_raw']))
			{
				$data_return  = $line['colspan_raw'];
				if (empty($line['colspan_raw']) && isset($line['skip_empty']) && $line['skip_empty'] == true)
				{
					$skip = true;
				}
			}
			else
			{
				$data_return  = sprintf('<h3>%s</h3><hr>', $line['title']);
			}
			if (! $skip) 
			{
				$data_return = '
					<div class="element-container">
						<div class="row">
							<div class="col-md-12 line_title">
							'.$data_return.'
							</div>
						</div>
					</div>
				';
			}
		}
		else
		{
			$val = "";
			if (! empty($line['input_type']))
			{
				$opt_extra = "";
				switch ($line['input_type'])
				{
					case 'checkbox':
						// $val = sprintf('<input type="checkbox" name="%s" value="%s" %s />', $line['input_name'], $line['input_value'], ($line['input_checked'] == true ? 'checked' : ''));
						$val = '
							<span class="radioset">
								<input type="radio" id="'.$line['input_name'].'_on" name="'.$line['input_name'].'" value="1" '.($line['input_checked'] == true ? 'checked' : '').'>
								<label for="'.$line['input_name'].'_on">'._('Yes').'</label>
								<input type="radio" id="'.$line['input_name'].'_off" name="'.$line['input_name'].'" value="" '.($line['input_checked'] != true ? 'checked' : '').'>
								<label for="'.$line['input_name'].'_off">'._('No').'</label>
							</span>
						';
						break;
					
					case 'number':
						$opt_extra .= empty($line['input_limit']['min']) ? '' :  sprintf(' min="%s"', $line['input_limit']['min']);
						$opt_extra .= empty($line['input_limit']['max']) ? '' :  sprintf(' max="%s"', $line['input_limit']['max']);

					case 'email':
					case 'text':
					case 'password':
						$opt_extra .= empty($line['input_size']) 	 ? '' :  sprintf(' size="%s"', $line['input_size']);
						$opt_extra .= empty($line['placeholder']) 	 ? '' :  sprintf(' placeholder="%s"', $line['placeholder']);
						$opt_extra .= empty($line['input_required']) ? '' :  ' required';

						$params_input = array(
							$line['input_type'],
							empty($line['input_id']) ? $line['input_name'] : $line['input_id'],
							$line['input_name'],
							$line['input_value'],
							empty($line['input_class']) ? '' : $line['input_class'],
							$opt_extra
						);
						$val = vsprintf('<input type="%s" id="%s" name="%s" value="%s" class="form-control %s" %s>', $params_input);
						break;

					case 'textarea':
						$opt_extra .= empty($line['input_size']['cols']) 	  ? '' :  sprintf(' cols="%s"', $line['input_size']['cols']);
						$opt_extra .= empty($line['input_size']['rows']) 	  ? '' :  sprintf(' rows="%s"', $line['input_size']['rows']);
						$opt_extra .= empty($line['input_size']['maxlength']) ? '' :  sprintf(' maxlength="%s"', $line['input_size']['maxlength']);

						$params_input = array(
							empty($line['input_id']) ? $line['input_name'] : $line['input_id'],
							$line['input_name'],
							empty($line['input_class']) ? '' : $line['input_class'],
							$opt_extra,
							$line['input_value'],
						);
						$val = vsprintf('<textarea id="%s" name="%s" class="form-control %s" %s>%s</textarea>', $params_input);
						break;

					case 'hidden':
						$params_input = array(
							empty($line['input_id']) ? $line['input_name'] : $line['input_id'],
							$line['input_name'],
							$line['input_value'],
						);
						$val = vsprintf('<input type="hidden" id="%s" name="%s" value="%s">', $params_input);
						break;

					case 'form':
						$params_input = array(
							empty($line['input_id']) ? $line['input_name'] : $line['input_id'],
							$line['input_name'],
							empty($line['method']) ? $line['method'] : 'post',
							$line['action']
						);
						$val = vsprintf('<form id="%s" name="%s" method="%s" action="%s">', $params_input);
						break;

					case 'raw':
						$val = $line['input_value'];
						break;

					default:
						dbug("Type not supported: Type > ". isset($line['input_type']) ? $line['input_type'] : "Is not definde!!");
				}
			}
			elseif (isset($line['value']))
			{
				$val = $line['value'];
			}

			if (empty($line['input_name'])) {
				// Set a name if it is not defined so that the help is displayed correctly.
				$line['input_name'] = md5(str_replace(".", "", microtime(true) ."-".  rand(1, 999)));
			}

			if ( in_array($line['input_type'], array("hidden", "form")) )
			{
				$data_return = $val;
			}
			else
			{
				if (! empty($line['btn_reset']))
				{
					if (! isset($line['default']))
					{
						$line['default'] = empty($line['placeholder']) ? '' : $line['placeholder'];
					}
					$val = '
    				<div class="input-group">
						'.$val.'
      					<span class="input-group-btn">
        					<button class="btn btn-default btn-reset-value" title="'._("Reset Default").'" type="button" value="'. $line['default'] .'">
								<i class="fa fa-undo" aria-hidden="true"></i>
							</button>
      					</span>
    				</div>
					';

					

				}
				$params_line = array(
					$line['input_name'],
					$line['title'],
					$line['input_name'],
					!empty($line['label_down']) ? $line['label_down'] : '',
					$val,
					$line['input_name'],
					$line['help']
				);
				$data_return = '
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="%s">%s</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="%s"></i>
											%s
										</div>
										<div class="col-md-9">%s</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="%s-help" class="help-block fpbx-help-block">%s</span>
							</div>
						</div>
					</div>
				';
				$data_return = vsprintf($data_return, $params_line);
			}
		}
	}
	return $data_return;
}


foreach ($table_lines as $line)
{
	if (! empty($line['type']) && $line['type'] == 'list' && isset($line['list']) && is_array($line['list']))
	{
		foreach ($line['list'] as $subLine)
		{
			$new_subLine = lineParse($subLine);
			if (! empty($new_subLine))
			{
				echo $new_subLine;
			}
		}
	}
	else
	{
		$new_line = lineParse($line);
		if (! empty($new_line))
		{
			echo $new_line;
		}
	}
}
?>