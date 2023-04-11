<?php

/**
 *
 * If check box GUI element does not exist add it
 *
 */
if(!class_exists("gui_checkbox")) {
	class gui_checkbox extends guiinput {
		public function __construct() {
		}
		function gui_checkbox($elemname, $checked=false, $prompttext='', $helptext='', $value='on', $post_text = '', $jsonclick = '', $disable=false) {
			$parent_class = get_parent_class($this);
			if (is_callable('parent::$parent_class')) {
				parent::$parent_class($elemname, '', $prompttext, $helptext);
			} else {
				parent::__construct($elemname, '', $prompttext, $helptext);
			}

			$itemchecked = $checked ? 'checked' : '';
			$disable_state = $disable ? 'disabled="true"' : '';
			$js_onclick_include = ($jsonclick != '') ? 'onclick="' . $jsonclick. '"' : '';
			$tabindex = function_exists("guielement::gettabindex") ? "tabindex=" . guielement::gettabindex() : "";

			$this->html_input = "<input type=\"checkbox\" name=\"$this->_elemname\" id=\"$this->_elemname\" $disable_state $tabindex value=\"$value\" $js_onclick_include $itemchecked/>$post_text\n";
		}
	}
}
