<?php

defined('BASEPATH') or exit('No direct script access allowed');
   
    class Sms extends AdminController
    {
    public function __construct()
    {
    parent::__construct();
    $this->load->model('Office_models');
    
    }
    
    public function index(){
    
    $data['title']= 'SMS Spark';
    $this->load->view('index',$data);
    
    }
    
    public function ajax_data(){
     $id = $this->input->post('id'); 
     $data=$this->Office_models->get_body($id);
     echo json_encode($data);
     //print_r($data);die('===');
    
    }
    
    
        
    public function ajax_data_edit(){
     $id = $this->input->post('id'); 
     $data=$this->Office_models->get_body_edit($id);
     echo json_encode($data);
     //print_r($data);die('===');
    
    }
    
    public function num_count() {
       $input = $this->input->post('phonenumber');
       $values = explode(",", $number);
       $count = count($values);
       echo $count;
   }
    
  
        public function send_sms(){
            
         $number = $this->input->post('phonenumber');   
         
         $phone = explode(",",$number);
         
         
         for($i=0;$i<count($phone);$i++){
        
        $sender_id = 'incras' ;
        $auth_key =  'mZuxt2he7oG8MdbS9LEgYpFJkXayTcrKB153vOfNwWqRUHsil4S7jUzkcvI9quTQ6RYFo8t4KanxLr23';
    
        $template_id = $this->input->post('template_id');
        
       $phonenumber = $phone[$i];
        
         $message = $this->input->post('message');
   
      preg_match_all('/{(.*?)}/', $message, $matches);
      
      $text='';
       foreach ($matches[1] as $a ){
      $text .=   $a."|";
       }
   
       $msg=  rtrim($text,"|");
       //die('===');
     
        $fields = array(
        "sender_id" => "$sender_id",
        "message" => "$template_id",
        "variables_values" => "$msg",
        "route" => "dlt",
        "numbers" => "$number",
        );
        
        $curl = curl_init();     
        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($fields),
        CURLOPT_HTTPHEADER => array(
        "authorization: $auth_key",
        "accept: */*",
        "cache-control: no-cache",
        "content-type: application/json"
        ),
        ));
        
        
        $response = curl_exec($curl);
curl_close($curl);
$arr = json_decode($response);

$status = 'Sent';
if($arr->return != 1) {
    $status = 'Failed';
    $this->session->set_userdata('smsnotsent', '1');
} else {
    $this->session->set_userdata('smssent', '1');
    $text = $this->input->post('message');
//
$number = $this->input->post('phonenumber');   
$phone = explode(",",$number);
$total_numbers = count($phone);

for($i=0;$i<$total_numbers;$i++){
    // your existing code here
    // ...
    $text = $this->input->post('message');
    $characters_count = strlen($text);
    $credits = ceil($characters_count / 160);
    $total_credits = $credits * $total_numbers;

    // update the credit
    $this->db->set('ccredit', 'ccredit-'.$total_credits, FALSE);
    $this->db->update(db_prefix().'_smscredits'); }
//
}

$data = array(
    'ltemplate_id' => $this->input->post('template_id'),
    'ltemplate_body' => $this->input->post('message'),
    'lnumbers' => $this->input->post('phonenumber'),
    'ltemplate_status' => $status,
    'lcredit_minus' => $total_credits,
    'created_at' => date('Y-m-d H:i:s'),
);
$this->db->insert(db_prefix().'_smslogs', $data);
redirect($_SERVER['HTTP_REFERER']);
        
        }
        }
        
        
        
        
        }
