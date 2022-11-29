<?php

/**
 *
 * Radio button component that supports onclick and does not include the element name in the value
 * @author michaely
 *
 */
class cxpanel_radio extends guiinput {
	function __construct($elemname, $valarray, $currentvalue = '', $prompttext = '', $helptext = '', $disable=false) {
		if (!is_array($valarray)) {
			trigger_error('$valarray must be a valid array in gui_radio');
			return;
		}

		$parent_class = get_parent_class($this);
		if (is_callable('parent::$parent_class')) {
			parent::$parent_class($elemname, $currentvalue, $prompttext, $helptext);
		} else {
			parent::__construct($elemname, $currentvalue, $prompttext, $helptext);
		}

		$this->html_input = $this->buildradiobuttons($valarray, $currentvalue, $disable);
	}

	function buildradiobuttons($valarray, $currentvalue, $disable=false) {
		$output = '';
		$output .= '<span class="radioset">';

		$count = 0;
		foreach ($valarray as $item) {
			$itemvalue = (isset($item['value']) ? $item['value'] : '');
			$itemtext = (isset($item['text']) ? $item['text'] : '');
			$itemchecked = ((string) $currentvalue == (string) $itemvalue) ? ' checked=checked' : '';
			$onClick = ((isset($item['onclick']) && $item['onclick'] != "") ? " onclick=\"" . $item['onclick'] . "\"" : "");

			$tabindex = guielement::gettabindex();
			$disable_state = $disable ? 'disabled="true"':'';
			$output .= "<input type=\"radio\" name=\"$this->_elemname\" id=\"$this->_elemname$count\" $disable_state tabindex=\"$tabindex\" value=\"$itemvalue\"$onClick $itemchecked/><label for=\"$this->_elemname$count\">$itemtext</label>\n";
			$count++;
		}
		$output .= '</span>';
		return $output;
	}
}