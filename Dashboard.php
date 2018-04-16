<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/dashboard
	 *	- or -
	 * 		http://example.com/index.php/dashboard/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/dashboard/<method_name>
	 * @see https://codeigniter.com/user_guide/general/urls.html
	 */
	
	public function __construct(){
		
		parent::__construct();
		
		// Load session library
		$this->load->library('session');
		$this->load->library('currency_converter');
		$this->load->library('form_validation');
		$this->load->library('timezones');
		
		// load  model classes //
		$this->load->model('Entry_model');
		$this->load->model('Heading_model');
		$this->load->model('accounts_model');
		$this->load->model('product_model');

		// load helper classes //
		$this->load->helper('date');
		$this->load->helper('cookie');
		$this->load->helper('products');
		$this->load->helper('common');
		$this->load->helper('download');
		$this->load->helper('userinfo');
		// load helpers //
		$this->load->helper('accesscontrol');
		
		if(is_logged_in() === false){
			/**
			* check if user logged in
			* redirect to login if not
			*/
			redirect('login', 'refresh');
		}
		$this->user_data 	= $this->session->userdata('logged_in');
		$this->user_id 		= $this->user_data['id'];
		set_no_cache_headers(); // set no cache headers
		$this->closeAlert 	= '<a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
	}

	public function index($headingId = NULL){
		/**
		*	main page of dashboard
		*/
		$data['users_list'] = $this->accounts_model->get_all_users();
		$this->load->library('pagination');
 		
		if($headingId != NULL){
			/**
			 * load the entries related to the heading entry
			 */
			$data['showAllEntries'] = TRUE;
			$data['heading_data'] 	= $this->Heading_model->get_heading($headingId);
			$checkHScript 			= $this->Heading_model->isOwnHeadingScript($headingId);
			
			if($checkHScript === 'pre-defined'){
				/**
				* pre-defined headings
				*/
				if($headingId == 1){
					/**
					* todays headings
					*/
					$todays_entry = $this->Entry_model->get_today_entries();
					
					if($todays_entry !== false)
						$data['entry'] =  $todays_entry;
					else{
						$this->session->set_flashdata('nothingFound', '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> No matching entry found for Today\'s Actions'.$this->closeAlert.'</div>');
						$data['entry'] = '';
					}
				
				} elseif($headingId == 2){
					/**
					* this week headings
					*/
					$week_entries = $this->Entry_model->get_this_week_entries();
					
					if($week_entries !== false)
						$data['entry'] =  $week_entries;
					else{
						$this->session->set_flashdata('nothingFound', '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> No matching entry found for This Week\'s Actions'.$this->closeAlert.'</div>');
						$data['entry'] = '';
					}
				} 
				
				elseif( ($headingId ==4) || ($headingId ==7) || ($headingId == 68) ){
					/**
					* Entries in action & Inactive action
					*/
					$entries = $this->Heading_model->get_entries_by_heading($headingId);
					$data['heading_data'] = $this->Heading_model->get_heading($headingId);
					
					if($entries != FALSE){
						/**
						* entries found
						*/
						$this->Entry_model->unset_session_var('nothingFound');
						$data['entry'] = $entries;
					
					} else{
						$this->session->set_flashdata('nothingFound', '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> No matching result found'.$this->closeAlert.'</div>');
						$data['entry'] = '';
					}
				}
				
			} elseif($checkHScript === true){
				/**
				* self refrencing heading
				*/
				$entries = $this->Heading_model->get_entries_by_heading($headingId);
				$data['heading_data'] = $this->Heading_model->get_heading($headingId);
				
				if($entries != FALSE){
					/**
					* entries found
					*/
					$this->Entry_model->unset_session_var('nothingFound');
					$data['entry'] = $entries;
				
				} else{
					$this->session->set_flashdata('nothingFound', '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> No matching result found'.$this->closeAlert.'</div>');
					$data['entry'] = '';
				}
			
			}  else{
				/**
				* non-self refrencing heading
				*/
				$e_per_page       = $checkHScript['per_page'];
				$script_rules     = explode(',,', $checkHScript['rules']);
				// run rules //
				$entries_by_rules = $this->Heading_model->getEntriesByRules($script_rules);
				if($entries_by_rules != FALSE){
					$data['entry']    = $entries_by_rules;
				} else{
					$this->session->set_flashdata('nothingFound', '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> No matching result found'.$this->closeAlert.'</div>');
					$data['entry'] = '';
				}

			}
			
			/*$total_rows = $this->Entry_model->count_entries();
			
			$per_page   = 5; $segment = 4; 
			$page       = $this->input->get('entries_page'); 
			$limit      = $per_page;
			$start      = ($page !== null) ? $limit*($page-1) : 0;
			$pagination = init_pagination('dashboard/', $total_rows, $per_page, $segment);*/
				
		
		}else{
			/**
			 * load the entries 
			 * related to the search
			 */

			$data['showAllEntries'] = FALSE;
			$this->form_validation->set_rules('searchInput', 'Search field', 'trim|required'); // set validation rules //
			$advanceSearch    = $this->input->post('betweenRange'); // advance search
			$show_all_entries = $this->input->post('showAllEntries');
			
			// run form validation //
			if($this->form_validation->run() !== FALSE){
				/* form validation succeeded */
				$searchInput  = $this->input->post('searchInput');
				$searchIndex  = $this->input->post('searchCol');
				$searchSubmit = $this->input->post('simpleSearch');
				
				if($searchSubmit == 'Search'){
					$searchResult = $this->Entry_model->search($searchInput, $searchIndex);
					
					if($searchResult != false){
						$i = 0;	$inc = 0;
						$this->Entry_model->unset_session_var('nothingFound');
						
						foreach($searchResult as $search){
							$userIds[$i] = $search['user_id'];
							$i++;
						}
						$user_ids_string = implode(',', $userIds);
						$entriesArray    = $this->Entry_model->get_entries_by_id($userIds);

						foreach($entriesArray as $array){
							
							foreach($array as $entry){
								$entries[$inc]=$entry;
								$inc++;
							}
						}
						
						$data['entry'] = $entries;
						delete_cookie('search_user_ids');
						$searchCookie = array(
					        'name'   => 'user_ids',
					        'value'  => $user_ids_string,
					        'expire' => time()+3600,
					        'path'   => '/',
					        'prefix' => 'search_',
				        );
				        set_cookie($searchCookie);
				        $data['showAllEntries'] = TRUE; // show all entries button //
					
					} else{
						$this->session->set_flashdata('nothingFound', '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> No matching result found'.$this->closeAlert.'</div>');
						$data['entry'] = '';
					}
				}
			
			} elseif($advanceSearch == 'Search'){
				/**
				 * load entries 
				 * related to advanced search [by custom date]
				 * 'advance search' [button] [dashboard]
				 */
				$datefilter   = $this->input->post('datefilter');
				$date         = explode(' - ', $datefilter);
				$searchResult = $this->Entry_model->get_entries_by_date($date[0], $date[1]);
				
				if($searchResult != false){
					/**
					* Entries found
					*/
					$this->Entry_model->unset_session_var('nothingFound');
					$data['entry'] = array_unique($searchResult);
				
				} else{
					$this->session->set_flashdata('nothingFound', '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> No matching result found'.$this->closeAlert.'</div>');
					$data['entry'] = '';
				}

			} elseif($show_all_entries == 'show'){
				/**
				* 'Show all entries' button is clicked
				* delete the searched entries cookie
				*/
				$data['showAllEntries'] = FALSE;
				delete_cookie('search_user_ids');
				header('Location: '.base_url('dashboard'));
			
			} elseif(!(is_null(get_cookie('search_user_ids')))){
				/**
				* if cookie exist show searched result
				*/
				$data['showAllEntries'] = TRUE; // show all entries button //
				$userIds 				= explode(',', get_cookie('search_user_ids'));
				$entriesArray 			= $this->Entry_model->get_entries_by_id($userIds);
				$i = 0;
				
				foreach($entriesArray as $array){
					
					foreach($array as $entry){
						$entries[$i] = $entry;
						$i++;
					}
				}
				
				$data['entry'] = $entries;

			} else{
				/**
				 * default entries
				 * all entries inthe database
				 */
				$total_rows 		= $this->Entry_model->count_entries();
				$per_page   		= 5; 		$segment = 3; 
				$page       		= $this->input->get('entries_page'); 
				$limit      		= $per_page;
				$start      		= ($page !== null)? $limit*($page-1) : 0;
				$pagination 		= init_pagination('dashboard/', $total_rows, $per_page, $segment);
				$data['pagination'] = $this->pagination->create_links();
				$get_entries		= $this->Entry_model->get_all_entries($start, $limit);
				
				if(is_array($get_entries)){
					$data['entry']		= array_reverse($get_entries);
				} else{
					$this->session->set_flashdata('nothingFound', '<div class="alert alert-info"><i class="fa fa-exclamation-circle"></i> No entry found! '.$this->closeAlert.'</div>');
					$data['entry'] = '';
				}
			}
		}

		

		$data['headings'] 		= $this->Heading_model->get_headings();
		$data['countries'] 		= $this->currency_converter->getCountries();
		$data['action'] 		= $this->Entry_model->action_types();
		$data['timezones']		= $this->timezones->getTimezones();
		$data['utcTime'] 		= $this->timezones->getUtcTime();
		$header_data['title'] 	= "Dashboard";

		$this->load->view('templates/header', $header_data);
		$userInfo = $this->accounts_model->get_user_information($this->user_id);
		
		if(array_key_exists('preferred_currency', $userInfo))
			$user_currency = $userInfo['preferred_currency'];

		// getting the currency values //
		if(isset($user_currency))
			$data['user_currency'] = $user_currency;
		 
		// getting user timezone //
		if(array_key_exists('timezone', $userInfo))
			$data['timezone'] = $userInfo['timezone'];
		
		$data['action_page'] 		= FALSE;
		$data['entry_model'] 		= $this->Entry_model;
		$data['session_currency']	= $this->user_data['currency'];
		

		$this->load->view('dashboard_view', $data);
	}
	
	public function changeCurrency(){
		/**
		* Change the currency of the posted data 
		* to USD and vice-versa
		*/
		$currency = $this->input->post('currency');
		$result['first'] 	= $this->currency_converter->convert(array('amount'=>1, 'from'=>$currency, 'to'=>'USD'));
		$result['second'] 	= $this->currency_converter->convert(array('amount'=>1, 'from'=>'USD', 'to'=>$currency));
		echo $result['first'].' '.$result['second'];
	}
	
	public function convertCurrency(){
		/**
		* convert the posted currency prices
		* currency price > target currency
		*/
		$curr 			= $this->input->post('currency');
		$toCurr 		= $this->input->post('toCurrency');
		$price 			= $this->input->post('price');
		$convertedPrice = $this->currency_converter->convert(array('amount'=>$price, 'from'=>$curr, 'to'=>$toCurr));
		echo $convertedPrice;
	}

	public function changeTimezone(){
		/**
		* convert the posted 'Timezone'
		* to the current time
		*/
		$timezone 	= $this->input->post('timezone');
		$time 		= $this->timezones->getTime($timezone);
		echo $time;
	}
	public function getCurrencySymbol(){
		/**
		* change the posted currency code 
		* to it's currency symbol
		*/
		$currency 	= $this->input->post('currency');
		$symbol 	= $this->currency_converter->get_currency_symbol($currency);
		echo $symbol;
	}

	public function editUser(){
		/**
		* edit or update the user details 
		* from the dashboard quick 'edit user' button
		*/

		// set form-validation rules //
		$this->form_validation->set_rules('fName', 'First Name', 'trim|required');
		$this->form_validation->set_rules('lName', 'Last Name', 'trim|required');
		$this->form_validation->set_rules('email', 'Email', 'trim|required');

		// run form validation //
		if($this->form_validation->run()!== FALSE){
			/**
			* validation succeeded
			* get posted data
			*/
			$first_name = $this->input->post('fName');
			$last_name 	= $this->input->post('lName');
			$email 		= $this->input->post('email');
			$submit 	= $this->input->post('updateUser');//== update

			if($submit === "update"){
				// form submitted //
				$info_type   = array('first_name', 'last_name', 'email');
				$info_text   = array($first_name, $last_name, $email);
				$update_info = $this->accounts_model->update_user_info($this->user_id, $info_type, $info_text);
				
				$this->session->set_flashdata('user_info_updated', '<div class="alert alert-success">Details updated successfully!'.$this->closeAlert.'</div>');
				
				redirect('dashboard', 'refresh');
			} 
		}
	}

	public function setCurrency(){
		/**
		* set the currency of the user
		* when changed from the currency selector
		*/
		$currency 		= $this->input->post('currency');
		$session_array 	= array(
		 		'id' 		=> $this->user_data['id'],
		 		'username' 	=> $this->user_data['username'],
		 		'usertype' 	=> $this->user_data['usertype'],
		 		'currency' 	=> $currency
		 	);
		$this->session->set_userdata('logged_in', $session_array);
	}

	public function downloadFile() {
		$path = $this->input->post('path');
		$name = $this->input->post('title');
		$data = file_get_contents($path); // Read the file's contents
		force_download($name, $data);
	}

	public function addnew_product_from_dash(){
		/* collect product data */
		$action 							= $this->input->post('product_action');
		$entry_id 							= $this->input->post('entry_id');
		$product['supplier_id']             = 1;
        $product['product_code']            = '';
		$product['product_title']			= $this->input->post('p_name');
		$product['product_url']				= $this->input->post('p_url');
		$product['stock_volume']            = '';
        $product['store_location']          = '';
        $product['mo_quantity']             = 1;
        $product['notes']                   = '';
		$product['creation_date']           = date('Y-m-d H:i:s');
        $product['update_date']             = date('Y-m-d H:i:s');
        $product['inventory_author']        = $this->user_id;
        $product['update_author']           = $this->user_id;
		
		$product_exists = $this->product_model->product_exists($product['product_url']);
		$add_inv	= false;
		
		if($product_exists === FALSE){
			$add_inv 	= $this->product_model->addInventory($product); //add product
		
		} else{

			if($action == "save"){
				$add_inv = $product_exists; // product exists in database already

			} elseif($action == "update"){
				$update_inv_id 	= $product_exists;
			}
			
		} 
		$currency 				= $this->input->post('currency');
		$prices['retail'] 		= $this->input->post('retail_price');
		$prices['wholesale'] 	= $this->input->post('wholesale_price');
		$prices['cost'] 		= $this->input->post('cost_price');
		
		$this->load->model('Action_model');

		if($add_inv !== false){
			/*
			** product added, create quotes
			*/ 
			$quotes 			= $this->product_model->add_quotes($add_inv, $prices, $currency, date('Y-m-d H:i:s') );
			
			if($quotes !== false){
				/*
				** add quotes to the entry ['action_content']
				*/
				$action 		= array();
				$quotes 		= implode(',', $quotes);
				$action['entry_id'] 		    = 	$entry_id;
				$action['action_type']			=   20;
				$action['action_source'] 		= 	base_url('dashboard');
				$action['action_direction'] 	= 	"Internal"; 	// to be confirmed
				$action['action_status'] 		= 	"Completed";
				$action['action_notes'] 	    = 	'';			// to be added later
				$action['action_content'] 		= 	'quotes:'.$quotes .'|added';
				$action['date'] 	    		= 	date('Y-m-d H:i:s');
				$action['action_schedule'] 		= 	date('Y-m-d H:i:s');
				$action['action_author'] 		= 	$this->user_id;
				//$update_content = $this->Entry_model->update_entry_quote_content($entry_id, $quotes);
				
				$add_admin_action  = $this->Action_model->save_action($action);

				if($add_admin_action !== false){
					echo 'success';
				} else{
					echo 'error';
				}
			}
		} elseif( (isset($update_inv_id)) && ($update_inv_id !== false) ){
			/*
			** update the inventory and quotes table
			*/
			$action_id 		= $this->input->post('action_id');
			$quotes['retail']['qid'] 	= $this->input->post('retail_qid');
			$quotes['wholesale']['qid'] = $this->input->post('wholesale_qid');
			$quotes['cost']['qid'] 		= $this->input->post('cost_qid');

			$quotes['retail']['price']		= $prices['retail'];
			$quotes['wholesale']['price']	= $prices['wholesale'];
			$quotes['cost']['price']		= $prices['cost'];
			// call the update function inside entry_model 
			$updateProduct 	= $this->Entry_model->update_inventory_and_quotes($update_inv_id, $entry_id, $product['product_title'], $quotes, $currency);
			
			if($updateProduct !== false){
				$content_quotes	=	$quotes['retail']['qid'].','.$quotes['wholesale']['qid'].','.$quotes['cost']['qid'];
				$action 	= array();
				$action['entry_id'] 		    = 	$entry_id;
				$action['action_type']			=   20;
				$action['action_source'] 		= 	base_url('dashboard');
				$action['action_direction'] 	= 	"Internal"; 	// to be confirmed
				$action['action_status'] 		= 	"Completed";
				$action['action_notes'] 	    = 	'';			// to be added later
				$action['action_content'] 		= 	'quotes:'.$content_quotes.'|updated';
				$action['date'] 	    		= 	date('Y-m-d H:i:s');
				$action['action_schedule'] 		= 	date('Y-m-d H:i:s');
				$action['action_author'] 		= 	$this->user_id;
				
				$add_admin_action  = $this->Action_model->save_action($action);
				
				if($add_admin_action !== false)
					echo "success";
				else
					echo "error2";
			
			} else{
				echo "error1";
			}
		} elseif($add_inv === false){
			/*
			** some error occured while saving the product
			*/
			echo 'error';
		} 
	}

	public function addproduct($entry_id){
		/* Load the helpers required */
		$this->load->helper('settings');

		/* set header */
		$header_data['title']  = 'Add Product To Entry';
		$this->load->view('templates/header', $header_data);
		
		/* form validation */
		$this->form_validation->set_rules('product_url', 'Product Url', 'trim|required');	
		$this->form_validation->set_rules('product_name', 'Product Name', 'trim|required');	
		$this->form_validation->set_rules('retail_price', 'Retail Price', 'trim|required');
		$this->form_validation->set_rules('wholesale_price', 'Wholesale Price', 'trim|required');
		$this->form_validation->set_rules('cost_price', 'Cost Price', 'trim|required');

		if($this->form_validation->run() !== FALSE ){
			/* collect the posted data */
			$product_name	= $this->input->post('product_name');
			$product_url	= $this->input->post('product_url');
			$retail_price	= $this->input->post('retail_price');
			$wholesale_price= $this->input->post('wholesale_price');
			$cost_price		= $this->input->post('cost_price');
			$currency 		= $this->input->post('productCurrency'); 
			$action_notes 	= $this->input->post('action_notes');
			$entry_section  = $this->input->post('entry_section');
			$submit 		= $this->input->post('save');

			if($submit == 'Save'){
				/**
				 * form submitted
				 */
				$product_exists = $this->product_model->product_exists($product_url);
				$add_inv	= false;
		
				if($product_exists === FALSE){
					/**
					 * product doesn't exists, create new product
					 */
					$product = array();
					$product['supplier_id']             = 1;
			        $product['product_code']            = '';
					$product['product_title']			= $product_name;
					$product['product_url']				= $product_url;
					$product['stock_volume']            = '';
			        $product['store_location']          = '';
			        $product['mo_quantity']             = 1;
			        $product['notes']                   = '';
					$product['creation_date']           = date('Y-m-d H:i:s');
			        $product['update_date']             = date('Y-m-d H:i:s');
			        $product['inventory_author']        = $this->user_id;
			        $product['update_author']           = $this->user_id;
					$add_inv 			= $this->product_model->addInventory($product); //add product
				
				} else{
					$add_inv = $product_exists; // product exists in database already
				} 
				
				$prices['retail'] 		= $retail_price;
				$prices['wholesale'] 	= $wholesale_price;
				$prices['cost'] 		= $cost_price;
		
				$this->load->model('Action_model');

				if($add_inv !== false){
					/*
					** product added, create quotes
					*/ 
					$quotes 			= $this->product_model->add_quotes($add_inv, $prices, $currency, date('Y-m-d H:i:s') );
					
					if($quotes !== false){
						/*
						** add quotes to the entry ['action_content']
						*/
						$action 		= array();
						$quotes 		= implode(',', $quotes);
						$action['entry_id'] 		    = 	$entry_id;
						$action['action_type']			=   20;
						$action['action_source'] 		= 	base_url('dashboard');
						$action['action_direction'] 	= 	"Internal"; 	// to be confirmed
						$action['action_status'] 		= 	"Completed";
						$action['action_notes'] 	    = 	$action_notes;			// to be added later
						$action['action_content'] 		= 	'quotes:'.$quotes .'|added';
						$action['date'] 	    		= 	date('Y-m-d H:i:s');
						$action['action_schedule'] 		= 	date('Y-m-d H:i:s');
						$action['action_author'] 		= 	$this->user_id;
						//$update_content = $this->Entry_model->update_entry_quote_content($entry_id, $quotes);
						
						$add_admin_action  = $this->Action_model->save_action($action);

						if($add_admin_action !== false){
							/**
							 * action saved
							 */
							$this->session->set_flashdata('success', '<div class="alert alert-success">Product successfully added to the entry <a class="close" href="#" data-dismiss="alert" aria-label="close">&times;</a></div>');
							redirect('dashboard#'.$entry_section, 'refresh');

						} else{
							/**
							 * error saving action
							 */
							$this->session->set_flashdata('error', '<div class="alert alert-success">Some error occured while saving the Product to the entry! <a class="close" href="#" data-dismiss="alert" aria-label="close">&times;</a></div>');
							redirect('dashboard/addproduct/'.$entry_id, 'refresh');
						}
					}
				}
			}
		} else{
			$this->load->view('entry/addedit_product');
		}
		
	}

	public function editproduct($entry_id, $action_id){
		/* Load the helpers required */
		$this->load->helper('settings');
		$this->load->model('Action_model');

		/* set header */
		$header_data['title']  = 'Edit the product';
		$this->load->view('templates/header', $header_data);
		
		/* collect common posted data */
		$product_name	= $this->input->post('product_name');
		$product_url	= $this->input->post('product_url');
		$retail_price	= $this->input->post('retail_price');
		$wholesale_price= $this->input->post('wholesale_price');
		$cost_price		= $this->input->post('cost_price');
		$currency 		= $this->input->post('productCurrency'); 
		$retail_qid 	= $this->input->post('retail_qid');
		$wholesale_qid 	= $this->input->post('wholesale_qid');
		$action_notes 	= $this->input->post('action_notes');
		$entry_section  = $this->input->post('entry_section');
		$cost_qid 		= $this->input->post('cost_qid');
		$invt_id 		= $this->input->post('invt_id');
		
		/* form validation */
		$this->form_validation->set_rules('product_url', 'Product Url', 'trim|required');	
		$this->form_validation->set_rules('product_name', 'Product Name', 'trim|required');	
		$this->form_validation->set_rules('retail_price', 'Retail Price', 'trim|required');
		$this->form_validation->set_rules('wholesale_price', 'Wholesale Price', 'trim|required');
		$this->form_validation->set_rules('cost_price', 'Cost Price', 'trim|required');
		$this->form_validation->set_rules('retail_qid', 'Retail Quote', 'trim|required');
		$this->form_validation->set_rules('wholesale_qid', 'Wholesale Quote', 'trim|required');
		$this->form_validation->set_rules('cost_qid', 'Cost Quote', 'trim|required');

		if($this->form_validation->run() !== FALSE ){
			/* collect the posted data */
			
			$submit 		= $this->input->post('save');

			if($submit == 'Save'){
				/**
				 * form submitted
				 */
				/* check if product exists */
				$product_exists = $this->product_model->product_exists($invt_id);
				// moderate this function later to check the inventory id of product with the product url

				if($product_exists === FALSE){ 
					/**
					 * product doesn't exists, show error
					 */
					echo '<div class="alert alert-warning">Product doesn\'t exists in database!<a class="close" href="#" data-dimiss="alert" aria-label="close">&times;</a></div>';
				
				} else{
					$update_inv_id = $product_exists; // product exists 

					if( (isset($update_inv_id)) && ($update_inv_id !== false) ){
						/*
						** update the inventory and quotes table
						*/
						$quotes['retail']['qid'] 	= $retail_qid;
						$quotes['wholesale']['qid'] = $wholesale_qid;
						$quotes['cost']['qid'] 		= $cost_qid;

						$quotes['retail']['price']		= $retail_price;
						$quotes['wholesale']['price']	= $wholesale_price;
						$quotes['cost']['price']		= $cost_price;

						// get the previous quotes content 
						$content_quotes	= $quotes['retail']['qid'].','.$quotes['wholesale']['qid'].','.$quotes['cost']['qid'];
						$quoteIds 		= explode(',', $content_quotes);
						$quoteResult    = $this->Entry_model->getQuoteDetails($quoteIds);

						foreach( $quoteResult as $row ){
								$i_id = $row['inventory_id'];
								$price		= str_replace(array('[',']'), array('(',')'), $row['price']);
								$content 	= $row['quote_id']."[".$row['inventory_id'].";".$price.";".$row['price_type']."]";
								$quote_details[] = $content;
							}
							$prod = getProductInfo($i_id);
							$product_nm = $prod[0]->product_title;
							$product_ur = $prod[0]->product_url;
							
							
							$quotesString = implode(',', $quote_details);

						// call the update function inside entry_model 
						$updateProduct 	= $this->Entry_model->update_inventory_and_quotes($update_inv_id, $entry_id, $product_name, $quotes, $currency, $product_url);
			
						if($updateProduct !== false){
							$quote_details 	= array();

							$action 		= array();
							$action['entry_id'] 		    = 	$entry_id;
							$action['action_type']			=   20;
							$action['action_source'] 		= 	base_url('dashboard');
							$action['action_direction'] 	= 	"Internal"; 	// to be confirmed
							$action['action_status'] 		= 	"Completed";
							$action['action_notes'] 	    = 	$action_notes;			
							$action['action_content'] 		= 	'editedQuotes:'.$quotesString.','.$product_ur.','.$product_nm.'|updated';
							$action['date'] 	    		= 	date('Y-m-d H:i:s');
							$action['action_schedule'] 		= 	date('Y-m-d H:i:s');
							$action['action_author'] 		= 	$this->user_id;
							
							$addedActionId  		= $this->Action_model->save_action($action);
							$update_action_notes 	= $this->Action_model->update_product_action_notes($action_id, $addedActionId);
							
							$historyData = array(
									"entry_id"  => $entry_id,
									"action_id" => (($addedActionId !== false)?$addedActionId:0),
									"edit_date" => date('Y-m-d H:i:s')
								);

							$saveEditHistory = $this->Action_model->insert_edit_history($historyData);

							if( ($addedActionId !== false) && ($saveEditHistory !== false)){
								$this->session->set_flashdata('success', "<div class='alert alert-success'>Product updated successfully!<a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a></div>") ;
								redirect('dashboard#'.$entry_section,'refresh');
							} else{
								$this->session->set_flashdata('error', "<div class='alert alert-danger'>Some error occured while saving the product update action! <a class='close' href='#' data-dismiss='alert' aria-label='close' >&times;</a></div>");
								$data['edit'] = 'edit';
								$this->load->view('entry/addedit_product', $data);
							}
							
						
						} else{
							$this->session->set_flashdata('error', "<div class='alert alert-danger'>Some error occured while updating the product! <a class='close' href='#' data-dismiss='alert' aria-label='close' >&times;</a></div>");
							$data['edit'] = 'edit';
							$this->load->view('entry/addedit_product', $data);
						}
					}
				}
				
			} else{
				$data = array(
					'product_name' 		=> $product_name,
					'product_url' 		=> $product_url,
					'retail_price' 		=> $retail_price,
					'wholesale_price' 	=> $wholesale_price,
					'cost_price'		=> $cost_price,
					'currency' 			=> $currency,
					'retail_qid' 		=> $retail_qid,
					'wholesale_qid' 	=> $wholesale_qid,
					'cost_qid' 			=> $cost_qid,
					'edit'				=> 'edit',
					'action_notes'		=> $action_notes,
					'invt_id'		    => $invt_id,
				);
				$this->load->view('entry/addedit_product', $data);
			}
		
		} else{
			$this->load->view('entry/addedit_product');
		}
	} 

	public function delete_product(){
		/**
		 * delete the product from frontend [dashboard-entrylist]
		 * collect data
		 */
		$this->load->model('Action_model');
		$action_id	 	= $this->input->post('actionId');
		$retail_qid 	= $this->input->post('retailQid');
		$wholesale_qid 	= $this->input->post('wholesaleQid');
		$cost_qid 		= $this->input->post('costQid');
		$delete_from    = $this->input->post('deleteFrom');
		$entry_id 		= $this->input->post('entryid');

		$quotes 		= $retail_qid .','. $wholesale_qid .','. $cost_qid;

		// get the quote details
		$quote_ids		= explode(',', $quotes);
		$result 		= $this->Entry_model->getQuoteDetails($quote_ids);
		$quoteDetails 	= array();
		
		foreach( $result as $row ){
			$price		= str_replace(array('[',']'), array('(',')'), $row['price']);
			$content 	= $row['quote_id']."[".$row['inventory_id'].";".$price.";".$row['price_type']."]";
			$quoteDetails[] = $content;
		}
		$quotesString = implode(',', $quoteDetails);

		/**
		 * call the entry_model function for deleting 
		 * delete from action_record, quote_record, inventory
		 */
		$del_result = $this->Entry_model->delete_product_from_entrylist($action_id, $retail_qid, $wholesale_qid, $cost_qid, $delete_from);

		if($del_result == true){
			$action['entry_id'] 		    = 	$entry_id;
			$action['action_type']			=   20;
			$action['action_source'] 		= 	base_url('dashboard');
			$action['action_direction'] 	= 	"Internal"; 	// to be confirmed
			$action['action_status'] 		= 	"Completed";
			$action['action_notes'] 	    = 	'';			// to be added later
			$action['action_content'] 		= 	'deletedquotes:'.$quotesString.'|deleted';
			$action['date'] 	    		= 	date('Y-m-d H:i:s');
			$action['action_schedule'] 		= 	date('Y-m-d H:i:s');
			$action['action_author'] 		= 	$this->user_id;
			
			$add_admin_action  = $this->Action_model->save_action($action);
			
			if($add_admin_action !== false){
				echo 'success';
			} else{
				echo 'error';
			}
						
		} else{
			echo 'error';
		}
	}

	public function addCustomer(){
		/*
		** add customer to the entry
		*/
	}

	public function editCustomer(){
		/*
		** edit the customer of entry
		*/
	}

	public function deleteCustomer(){
		/*
		** delete the customer of entry
		*/
	}

	public function cancelAction(){
		/**
		 * cancel the action which was scheduled to be done
		 */
		$actionId = $this->input->post('actionId');
		$actionNotes = $this->input->post('actionNotes');
		$entryId = $this->input->post('entryId');

		if($actionId !== false){
			$this->load->model('action_model');
			$update_action = $this->action_model->cancel_action($actionId, $entryId, $actionNotes, $this->user_id);
			
			if($update_action === true){
				echo "action cancelled";
			} else {
				echo "error";
			}

		} else{
			echo 'actionId can not be empty';
		}
		
	}
	
	
}