<?php

/**
 *
 * Select box component that supports multi select
 * @author michaely
 *
 */
if (! class_exists('cxpanel_multi_selectbox'))
{
	class cxpanel_multi_selectbox extends guiinput {
		public function __construct() {
		}
		function cxpanel_multi_selectbox($elemname, $valarray, $size = '5', $currentvaluearray = array(), $prompttext = '', $helptext = '', $canbeempty = true, $onchange = '', $disable=false) {
			if (!is_array($valarray)) {
				trigger_error('$valarray must be a valid array in gui_selectbox');
				return;
			}

			// currently no validation functions available for select boxes
			// using the normal $canbeempty to flag if a blank option is provided
			$parent_class = get_parent_class($this);
			if (is_callable('parent::$parent_class')) {
				parent::$parent_class($elemname, $currentvalue, $prompttext, $helptext);
			} else {
				parent::__construct($elemname, $currentvalue, $prompttext, $helptext);
			}


			$this->html_input = $this->buildselectbox($valarray, $size, $currentvaluearray, $canbeempty, $onchange, $disable);
		}

		// Build select box
		function buildselectbox($valarray, $size, $currentvaluearray, $canbeempty, $onchange, $disable) {
			$output = '';
			$onchange = ($onchange != '') ? " onchange=\"$onchange\"" : '';

			$tabindex = guielement::gettabindex();
			$disable_state = $disable ? 'disabled="true"':'';
			$output .= "\n\t\t\t<select multiple size=\"$size\" name=\"$this->_elemname[]\" id=\"$this->_elemname\" tabindex=\"$tabindex\" $disable_state $onchange >\n";
			// include blank option if required
			if ($canbeempty)
			$output .= "<option value=\"\">&nbsp;</option>";

			// build the options
			foreach ($valarray as $item) {
				$itemvalue = (isset($item['value']) ? $item['value'] : '');
				$itemtext = (isset($item['text']) ? $item['text'] : '');
				$itemselected = in_array($itemvalue, $currentvaluearray) ? ' selected' : '';

				$output .= "\t\t\t\t<option value=\"$itemvalue\"$itemselected>$itemtext</option>\n";
			}
			$output .= "\t\t\t</select>\n\t\t";

			return $output;
		}
	}
}