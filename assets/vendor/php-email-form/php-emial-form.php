<?php

	class Form extends Validation  {
		private $action;
		private $method;
		private $name;
		private $validation;
		private $vMessage;
		public $request = array();
		public $inputs = array();
		private $errors = array();
		private $cId;
		private $results = array();
		private $html = array();
		private $js = '';
						
		public function __construct($name = '', $action = '', $method = 'post', $validation = '', $vMessage = ''){
			$this -> action = $action;
			$this -> name = $name;
			$this -> validation = $validation;
			$this -> vMessage = $vMessage;
			if(trim(strtolower($method)) == "post" || trim(strtolower($method)) == "get"){
				$this -> method = trim(strtolower($method));
			} else {
				$this -> _error("wrong method");
			}
		}
		
		public function add(&$input){
			$this -> inputs[$input -> id] = $input;
		}
		public function addDatePicker($inputId, $minDate, $maxDate){
			$this -> js .= "$('#$inputId').datePicker({startDate:'$minDate', endDate:'$maxDate'});"."\n";
		}
		
		public function validate(){
			$request = $this -> getRequest();
			$this -> request = $request;
			$this -> getRequest();
			$errors[] = array();
			$count = 0;
			foreach ($this -> inputs as $id => $input){
				if(isset($request[$this -> name.'_sent']) && !empty($request[$this -> name.'_sent']))
					$this -> inputs[$id] -> value = $request[$id];
			}
			foreach ($this -> inputs as $id => $input){
				if($this -> validateInput($id) == false){
					$errors[] = $input;
					$count++;
					if(empty($request[$this -> name.'_sent']))
						$input -> error = false;
				}
			}
			if(!$this -> validateForm()) $count++;
			if(empty($request[$this -> name.'_sent'])) return false;
			$this -> errors = $errors;
			if($count > 0) return false;
			return true;
		}
		public function validateInput($id){
			$this -> cId = $id;
			$input =& $this -> inputs[$id];
			if($input -> validation){
				if(is_Array($input -> validation)){
					foreach ($input -> validation as $val){
						$input -> jsval .= $this -> _val($val);
					}
				} else {
					$input -> jsval = $this -> _val($input -> validation);
				}
			}
			$input -> jsval = substr($input -> jsval, 0, -1);
			if($this -> getResult()){
				$this -> results = array();
				return true;
			}
			$input -> error = true;
			$this -> results = array();
			return false;
		}
		public function validateForm(){
			if(!$this -> validation) return true;
			list($fname, $params) = explode('|', $this -> validation);
			$params = explode(':', $params);
		//	return call_user_method_array('ValidateAs'.$fname, $this, $params);
			return call_user_func_array(array(&$this, 'ValidateAs'.$fname), $params);
			
		}
		
		private function _val($val){
			@list($fname, $param) = explode('|', $val);
		//	$this -> results[$fname] = call_user_method_array('ValidateAs'.$fname, $this, array($this -> inputs[$this -> cId] -> value, $param));
			$this -> results[$fname] = call_user_func_array(array(&$this, 'ValidateAs'.$fname), array($this -> inputs[$this -> cId] -> value, $param));
			
		//	return call_user_method('ValidateAs'.$fname.'JS', $this, $param);
			return call_user_func(array(&$this, 'ValidateAs'.$fname.'JS'), $param);
		}
		private function getResult(){
			foreach ($this -> results as $result){
				
				if($result == false) return false;
			}
			return true;
		}
		private function getJS(){
			$rules = '';
			$msg = '';
			foreach ($this -> inputs as $input){
				$rules .= $input -> name.': {'.$input -> jsval.'},'."\n";
				$msg .= $input -> name.': "'.$input -> vMessage.'",'."\n";
			}
			$rules = substr($rules, 0, -2);
			$msg = substr($msg, 0, -2);
			
			$js = '
			<script type="text/javascript">
				$(document).ready(function(){
					'.$this -> js.'
					$("#'.$this -> name.' #form_loader").hide();
					$("#'.$this -> name.'")
						.validate({
							rules: {'.$rules.'},
							messages: {'.$msg.'},
							success: "valid",
							event: "blur",
							submitHandler: function(form){
								$("#'.$this -> name.' input[@type=submit]").hide();
								$("#'.$this -> name.' input[@type=image]").hide();
								$("#'.$this -> name.' #form_loader").show();
							}
						});
				});
			</script>';
			return $js;
		}
		
		public function show(){
			foreach ($this -> inputs as $id => $input){
				$this -> html[$id] = $input -> show();
			}
			$this -> html['form_start'] = $this -> getFormStart().$this -> getJS();
			$this -> html['form_end'] = $this -> getFormEnd();
			return $this -> html;
		}
		
		private function getRequest(){
			if($this -> method == "post"){
				return $_POST;
			} else {
				return $_GET;
			}
		}
		public function getErrors(){
			return $this -> errors;
		}
		private function getFormStart(){
			return '<form action="'.$this -> action.'" method="'.$this -> method.'" id="'.$this -> name.'" enctype="multipart/form-data">';
		}
		private function getFormEnd(){
			return '<div><input type="hidden" name="'.$this -> name.'_sent" value="1" /></div> </form>';
		}
		private function _error($text){
			echo $text;
			exit;
		}
		
	}
	abstract class Validation { 
		
		protected function ValidateAsNotEmpty($value, $param){
			if($value != "" && $value !== 0 && strlen($value) >= $param) return true;
			return false;
		}
		protected function ValidateAsNotEmptyJS($param){
			if($param)
				return "required: true, minLength: $param,";
			else 
				return "required: true,";
		}
		
		protected function ValidateAsEmail($value){
			if(!preg_match('|^[_a-z0-9.-]*[a-z0-9]@[_a-z0-9.-]*[a-z0-9].[a-z]{2,3}$|e', $value)) return false;
			return true;
		}
		protected function ValidateAsEmailJS(){
			return 'required: true, email: true,';
		}
		
		protected function ValidateAsSameAs($value, $param){
			if($value == $this -> inputs[$param] -> value) return true;
			return false;
		}
		protected function ValidateAsSameAsJS($param){
			return "equalTo: '#$param' ,";
		}
		
		// protected function ValidateAsUniqueEmail($value, $param){
		// 	if(!$value) return false;
		// 	db::query("SELECT * FROM ".DB_PREFIX."customers WHERE email=#", $value);
		// 	if(db::numRows() == 0) return true;
		// 	return false;
		// }
		protected function ValidateAsUniqueEmailJS($param){
			return '';
		}
		
		protected function ValidateAsDate($value, $param){
			if(preg_match("#^[0-9][0-9]/[0-9][0-9]/[0-9][0-9][0-9][0-9]$#", $value)){
				$param = explode(':', $param);
			
				$d = explode('/', $value);
				$s = explode('/', $param[0]);
				$f = explode('/', $param[1]);
						
				$date = mktime(0, 0, 0, $d[1], $d[0], $d[2]);
				$min = mktime(0, 0, 0, $s[1], $s[0], $s[2]);
				$max = mktime(0, 0, 0, $f[1], $f[0], $f[2]);
				
				if($date > $max || $date < $min) return false;
				return true;
			}
			
			return false;
		}
		protected function ValidateAsDateJS($param){
			return "required: true, date: true ,";
		}
		
		protected function ValidateAsRangeLength($value, $param){
			$param = explode(':', $param);
			if(strlen($value) < $param[0] || strlen($value) > $param[1]) return false;
			return true;
		}
		protected function ValidateAsRangeLengthJS($param){
			$param = explode(':', $param);
			return 'required: true, rangeLength:['.$param[0].','.$param[1].'],';
		}
		// protected function ValidateAsLogIn($p1, $p2){
		// 	$email = $this -> inputs[$p1] -> value;
		// 	$password = $this -> inputs[$p2] -> value;
		// 	if(!$email) return false;
		// 	db::query("SELECT * FROM ".DB_PREFIX."customers WHERE email=#", $email);
		// 	$row = db::fetch();
		// 	if($row['password'] == sha1($password)){
		// 		session::register($row['id'], $row['email'], $row['type']);
		// 		return true;
		// 	} else {
		// 		return false;
		// 	}
		// }
	
	}
	abstract class Input {
		public $class = '';
		public $disabled = '';
		public $id = '';
		public $name = '';
		public $size = '';
		public $style = '';
		public $value = '';
		public $readonly = '';
		public $label = '';
		public $validation = '';
		public $vMessage = ' ';
		public $jsval = '';
		public $error = false;
		
		public function __construct($options, $validation = '', $vMessage = ''){
			if($options['name'] && !isset($options['id']) && empty($options['id']))
				$options['id'] = $options['name'];
			if($options['id'] && !$options['name'])
				$options['name'] = $options['id'];
			
			if(is_array($options)){
				$this -> parseOptions($options);
			} else {
				$this -> id = $options;
				$this -> name = $options;
			}
			
			/*
			if(!$vMessage){
				$this -> vMessage = "Field $this->name not valid";
			} else {
				$this -> vMessage = $vMessage;
			}
			*/
		//	$this -> vMessage = '<img src=\"templates/img/error.png\" />';
		
			
			$this -> validation = $validation;
		}
		
		protected function parseOptions($options){
			foreach ($options as $key => $value){
				if(isset($this -> $key)){
					$this -> $key = $value;
				} else {
					$this -> _error("unknown variable $key");
				}
			}
		}
		
		// protected function getLoader(){
		// 	return '<img id="form_loader" src="'.SITE_URL.IMG_DIR.'loader.gif" />';
		// }
		protected function parseValidation($validation){
			if(is_array($validation)){
				foreach ($validation as $v){
				}
			} else {
				$this -> validation = $validation;
			}
		}
		
		protected function getCommonHtml(){
			$html = '';
			$class = 'class';
			if($this -> $class)
				$html .= ' class="'.$this -> $class.'"';
			if($this -> disabled)
				$html .= ' disabled="'.$this -> disabled.'"';
			if($this -> id)
				$html .= ' id="'.$this -> id.'"';
			if($this -> name)
				$html .= ' name="'.$this -> name.'"';
			if($this -> style)
				$html .= ' style="'.$this -> style.'"';
			if($this -> readonly)
				$html .= ' readonly="'.$this -> readonly.'"';
			return $html;
		}
		
		protected function showError(){
			
			if($this -> error){
				return '<label class="error"></label>';
			//	return '<label for="'.$this -> name.'" class="error">'.$this -> vMessage.'</label>';
			} else {
			//	return '<label class="error valid"></label>';
			}
		}
		
		protected function _error($text){
			echo $text;
		}
	}

	class inputCheckbox extends Input {
		public function show(){
			$class = 'class';
			if(!$this -> $class)
				$class = ' class="checkbox"';
						
			if(!$this -> value)
				$this -> value = '1';
			else
				$c = ' checked="checked"';
			
			$html = '<input '.$class.$c.' type="checkbox"';
			$html .= $this -> getCommonHtml();
			$html .= ' value="'.$this -> value.'"';
			$html .= ' />';
			$html .= $this -> showError();
			return $html;
		}
	}
	class inputFile extends Input {
		public function show(){
			$class = 'class';
			if(!$this -> $class){
				$class = ' class="file"';
			}
			
			$html = '<input '.$class.' type="file"';
			$html .= $this -> getCommonHtml();
			$html .= ' value="'.$this -> value.'"';
			$html .= ' />';
			$html .= $this -> showError();
			return $html;
		}
	}
	class inputHidden extends Input {
		public function show(){
			$class = 'class';
			if(!$this -> $class){
				$class = ' class="hidden"';
			}
			
			$html = '<input '.$class.' type="hidden"';
			$html .= $this -> getCommonHtml();
			$html .= ' value="'.$this -> value.'"';
			$html .= ' />';
			$html .= $this -> showError();
			return $html;
		}
	}
	class inputImage extends Input {
		private $src;
		public function __construct($options, $validation = array()){
			if(!is_array($options)){
				$o = array();
				$this -> src = $options;
				$o['name'] = 'submit';
				$o['id'] = 'submit';
			} else {
				$o = $options;
				$this -> src = $o['src'];
				unset($o['src']); 
			}
			parent::__construct($o, $validation);
		}
	
		public function show(){
			$class = 'class';
			if(!$this -> $class){
				$class = ' class="image"';
			}
			
			$html = '<input '.$class.' type="image"';
			$html .= $this -> getCommonHtml();
			$html .= ' src="'.$this -> src.'"';
			$html .= ' />';
			// $html .= $this -> getLoader();
			$html .= $this -> showError();
			return $html;
		}
	}
	class inputPassword extends Input {
		public function show(){
			$class = 'class';
			if(!$this -> $class){
				$class = ' class="password"';
			}
			
			$html = '<input '.$class.' type="password"';
			$html .= $this -> getCommonHtml();
			$html .= ' value="'.$this -> value.'"';
			$html .= ' />';
			$html .= $this -> showError();
			return $html;
		}
	}
	class inputRadio extends Input {
		public function show(){
			$class = 'class';
			if(!$this -> $class){
				$class = ' class="radio"';
			}
			
			$html = '<input '.$class.' type="radio"';
			$html .= $this -> getCommonHtml();
			$html .= ' value="'.$this -> value.'"';
			$html .= ' />';
			$html .= $this -> showError();
			return $html;
		}
	}
	class inputSubmit extends Input {
		public function __construct($options, $validation = array()){
			if(!is_array($options)){
				$o = array();
				$o['value'] = $options;
				$o['name'] = 'submit';
				$o['id'] = 'submit';
			} else {
				$o = $options;
			}
			parent::__construct($o, $validation);
		}
		
		public function show(){
			$class = 'class';
			if(!$this -> $class){
				$class = ' class="submit"';
			}
			
			$html = '<input '.$class.' type="submit"';
			$html .= $this -> getCommonHtml();
			$html .= ' value="'.$this -> value.'"';
			$html .= ' />';
			return $html;
		}
	}
	class inputText extends Input {
		public function show(){
			$class = 'class';
			if(!$this -> $class){
				$class = ' class="text"';
			}
			$html = '<input '.$class.' type="text"';
			$html .= $this -> getCommonHtml();
			$html .= ' value="'.$this -> value.'"';
			$html .= ' />';
			$html .= $this -> showError();
			return $html;
		}
	}
	class Select extends Input {
		public $fields = array();
		public $selected = '';
		public function show(){
			$html = '<select';
			$html .= $this -> getCommonHtml();
			$html .= ' >';
			foreach ($this -> fields as $value => $name) {
				if($value == 0) $value ='';
			
				if($this -> selected == $value || $this -> value == $value) $selected = ' selected="selected"';
				else $selected = '';
				$html .= '<option value="'.$value.'" '.$selected.' >'.$name.'</option>';
			}
			$html .= '</select>';
			$html .= $this -> showError();
			return $html;
		}
	}
	class Textarea extends Input {
		public function show(){
			$html = '<textarea';
			$html .= $this -> getCommonHtml();
			$html .= ' >';
			$html .= $this -> value;
			$html .= '</textarea>';
			$html .= $this -> showError();
			return $html;
		}
	}

?>