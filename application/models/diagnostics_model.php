<?php 
class Diagnostics_model extends CI_Model{
	function __construct(){
		parent::__construct();
	}
	function order_test(){
		$this->db->select('visit_id')->from('patient_visit')
		->where('hosp_file_no',$this->input->post('visit_id'))
		->where('visit_type',$this->input->post('patient_type'))
		->where('YEAR(admit_date)',$this->input->post('year'),false);
		$query=$this->db->get();
		$row=$query->row();
		$visit_id=$row->visit_id;
		$doctor_id=$this->input->post('order_by');
		$test_area_id=$this->input->post('test_area');
		$order_date_time=date("Y-m-d H:i:s",strtotime($this->input->post('order_date')." ".$this->input->post('order_time')));
		$order_status=0;
		$this->db->trans_start();
			$data=array(
				'visit_id'=>$visit_id,
				'doctor_id'=>$doctor_id,
				'test_area_id'=>$test_area_id,
				'order_date_time'=>$order_date_time,
				'order_status'=>$order_status
			);
			$this->db->insert('test_order',$data);
			$order_id=$this->db->insert_id();
			$sample_code=$this->input->post('sample_id');
			$sample_date_time = date("Y-m-d H:i:s");
			$specimen_type_id=$this->input->post('specimen_type');
			$sample_container_type=$this->input->post('sample_container');
			$sample_status_id=1;
			$data=array(
				'sample_code'=>$sample_code,
				'sample_date_time'=>$sample_date_time,
				'order_id'=>$order_id,
				'specimen_type_id'=>$specimen_type_id,
				'sample_container_type'=>$sample_container_type,
				'sample_status_id'=>$sample_status_id
			);
			$this->db->insert('test_sample',$data);
			$sample_id=$this->db->insert_id();
			$data=array();
			if($this->input->post('test_master'))
				foreach($this->input->post('test_master') as $test_master){
					$data[]=array(
						'order_id'=>$order_id,
						'sample_id'=>$sample_id,
						'test_master_id'=>$test_master,
						'group_id'=>0
					);
				}
			if($this->input->post('test_group')){
				foreach($this->input->post('test_group') as $test_group){
					$this->db->select('test_master.test_master_id,has_result')->from('test_master')->join('test_group_link','test_master.test_master_id=test_group_link.test_master_id')
					->join('test_group','test_group_link.group_id=test_group.group_id')
					->where('test_group.group_id',$test_group);
					$query=$this->db->get();
					$result=$query->result();
					foreach($result as $row){						
						$data[]=array(
							'order_id'=>$order_id,
							'sample_id'=>$sample_id,
							'group_id'=>$test_group,
							'test_master_id'=>$row->test_master_id
						);
					}					
						$data[]=array(
							'order_id'=>$order_id,
							'sample_id'=>$sample_id,
							'group_id'=>$test_group,
							'test_master_id'=>0
						);
				}
			}
			$this->db->insert_batch('test',$data);
		$this->db->trans_complete();
		if($this->db->trans_status()===FALSE){
				$this->db->trans_rollback();
				return false;
		}
		else return true;				
	}
	
	function get_tests_ordered($test_areas){
		$this->input->post('test_area')?$test_area=$this->input->post('test_area'):$test_area="";
		if(count($test_areas)==1){
			$test_area = $test_areas[0]->test_area_id;
		}
		if($this->input->post('from_date') && $this->input->post('to_date')){
			$from_date = date("Y-m-d",strtotime($this->input->post('from_date')));
			$to_date = date("Y-m-d",strtotime($this->input->post('to_date')));
		}
		else if($this->input->post('from_date') || $this->input->post('to_date')){
			$this->input->post('from_date')?$from_date=date("Y-m-d",strtotime($this->input->post('from_date'))):$from_date=date("Y-m-d",strtotime($this->input->post('to_date')));
			$to_date=$from_date;
		}
		else{
			$from_date = date("Y-m-d");
			$to_date=date("Y-m-d");
		}
		if($this->input->post('test_method_search') != ""){
			$this->db->where('tms.test_method_id',$this->input->post('test_method_search'));
		}
		if($this->input->post('hosp_file_no_search') && $this->input->post('patient_type_search')){
			$this->db->where('hosp_file_no',$this->input->post('hosp_file_no_search'));
			$this->db->where('visit_type',$this->input->post('patient_type_search'));
		}
		$this->db->select('test_id,test_order.order_id,test_sample.sample_id,test_method,
		test_name,department,patient.first_name, patient.last_name,
		staff.first_name staff_name,hosp_file_no,sample_code,specimen_type,sample_container_type,test_status',false)
		->from('test_order')
		->join('test','test_order.order_id=test.order_id')
		->join('test_sample','test_order.order_id=test_sample.order_id')
		->join('test_group','test.group_id=test_group.group_id','left')
		->join('test_master as ts','test.test_master_id=ts.test_master_id','left')
		->join('test_method tms','ts.test_method_id=tms.test_method_id','left')
		->join('staff','test_order.doctor_id=staff.staff_id','left')
		->join('patient_visit','test_order.visit_id=patient_visit.visit_id')
		->join('patient','patient_visit.patient_id=patient.patient_id')
		->join('department','patient_visit.department_id=department.department_id')
		->join('specimen_type','test_sample.specimen_type_id=specimen_type.specimen_type_id')
		->where("(DATE(order_date_time) BETWEEN '$from_date' AND '$to_date')") 
		->where('order_status !=',2)
		->where('ts.test_area_id',$test_area);
		$query=$this->db->get();
		return $query->result();
	}
	
	function get_tests_completed($test_areas){

		$this->input->post('test_area')?$test_area=$this->input->post('test_area'):$test_area="";
		if(count($test_areas)==1){
			$test_area = $test_areas[0]->test_area_id;
		}
		if($this->input->post('from_date') && $this->input->post('to_date')){
			$from_date = date("Y-m-d",strtotime($this->input->post('from_date')));
			$to_date = date("Y-m-d",strtotime($this->input->post('to_date')));
		}
		else if($this->input->post('from_date') || $this->input->post('to_date')){
			$this->input->post('from_date')?$from_date=date("Y-m-d",strtotime($this->input->post('from_date'))):$from_date=date("Y-m-d",strtotime($this->input->post('to_date')));
			$to_date=$from_date;
		}
		else{
			$from_date = date("Y-m-d");
			$to_date=date("Y-m-d");
		}
		if($this->input->post('test_method_search') != ""){
			$this->db->where('test_method.test_method_id',$this->input->post('test_method_search'));
		}
		if($this->input->post('hosp_file_no_search') && $this->input->post('patient_type_search')){
			$this->db->where('hosp_file_no',$this->input->post('hosp_file_no_search'));
			$this->db->where('visit_type',$this->input->post('patient_type_search'));
		}
		$this->db->select('test_id,test_order.order_id,test_sample.sample_id,test_method,test_name,department,patient.first_name, patient.last_name,
							staff.first_name staff_name,hosp_file_no,sample_code,specimen_type,sample_container_type,test_status')
		->from('test_order')
		->join('test','test_order.order_id=test.order_id')
		->join('test_sample','test_order.order_id=test_sample.order_id')
		->join('test_master','test.test_master_id=test_master.test_master_id')
		->join('test_method','test_master.test_method_id=test_method.test_method_id')
		->join('staff','test_order.doctor_id=staff.staff_id','left')
		->join('patient_visit','test_order.visit_id=patient_visit.visit_id'	)
		->join('patient','patient_visit.patient_id=patient.patient_id')
		->join('department','patient_visit.department_id=department.department_id')
		->join('specimen_type','test_sample.specimen_type_id=specimen_type.specimen_type_id')
		->where("(DATE(order_date_time) BETWEEN '$from_date' AND '$to_date')") 
		->where('test_master.test_area_id',$test_area);

		$query=$this->db->get();
		return $query->result();
	}
	
	function get_tests_approved($test_areas){
		$this->input->post('test_area')?$test_area=$this->input->post('test_area'):$test_area="";
		if(count($test_areas)==1){
			$test_area = $test_areas[0]->test_area_id;
		}
		if($this->input->post('from_date') && $this->input->post('to_date')){
			$from_date = date("Y-m-d",strtotime($this->input->post('from_date')));
			$to_date = date("Y-m-d",strtotime($this->input->post('to_date')));
		}
		else if($this->input->post('from_date') || $this->input->post('to_date')){
			$this->input->post('from_date')?$from_date=date("Y-m-d",strtotime($this->input->post('from_date'))):$from_date=date("Y-m-d",strtotime($this->input->post('to_date')));
			$to_date=$from_date;
		}
		else{
			$from_date = date("Y-m-d");
			$to_date=date("Y-m-d");
		}
		if($this->input->post('test_method_search') != ""){
			$this->db->where('test_method.test_method_id',$this->input->post('test_method_search'));
		}
		if($this->input->post('hosp_file_no_search') && $this->input->post('patient_type_search')){
			$this->db->where('hosp_file_no',$this->input->post('hosp_file_no_search'));
			$this->db->where('visit_type',$this->input->post('patient_type_search'));
		}
		$this->db->select('test_id,test_order.order_id,test_sample.sample_id,test_method,test_name,department,patient.first_name, patient.last_name,
							staff.first_name staff_name,hosp_file_no,sample_code,specimen_type,sample_container_type,test_status')
		->from('test_order')
		->join('test','test_order.order_id=test.order_id')
		->join('test_sample','test_order.order_id=test_sample.order_id')
		->join('test_master','test.test_master_id=test_master.test_master_id')
		->join('test_method','test_master.test_method_id=test_method.test_method_id')
		->join('staff','test_order.doctor_id=staff.staff_id','left')
		->join('patient_visit','test_order.visit_id=patient_visit.visit_id')
		->join('patient','patient_visit.patient_id=patient.patient_id')
		->join('department','patient_visit.department_id=department.department_id')
		->join('specimen_type','test_sample.specimen_type_id=specimen_type.specimen_type_id')
		->where("(DATE(order_date_time) BETWEEN '$from_date' AND '$to_date')") 
		->where('test_status',2)
		->where('test_master.test_area_id',$test_area);
		$query=$this->db->get();
		return $query->result();
	}
	
	function get_order(){
		$order_id=$this->input->post('order_id');
		$this->db->select('test.test_id,test.test_master_id,test_group.group_id,test_order.order_id,test_order.order_date_time,test.reported_date_time,test_sample.sample_id,test_method,accredition_logo,
		IFNULL(test_name,group_name)test_name,department.department,unit_name,area_name,age_years,age_months,age_days,patient.gender,patient.first_name, patient.last_name,visit_type,
		order_date_time,hosp_file_no,sample_code,specimen_type,sample_container_type,
		a_staff.staff_id a_id,a_staff.email a_email,a_staff.first_name a_first_name,a_staff.phone a_phone,
		u_staff.staff_id u_id,u_staff.email u_email,u_staff.first_name u_first_name,u_staff.phone u_phone,
		d_staff.staff_id d_id,d_staff.email d_email,d_staff.first_name d_first_name,d_staff.phone d_phone,
		IFNULL(ts.binary_result,test_group.binary_result) binary_result,
		IFNULL(ts.numeric_result,test_group.numeric_result) numeric_result,
		IFNULL(ts.text_result,test_group.text_result) text_result,
		IFNULL(ts.binary_positive,test_group.binary_positive) binary_positive,
		IFNULL(ts.binary_negative,test_group.binary_negative) binary_negative,
		IFNULL(lus.lab_unit,lug.lab_unit) lab_unit,
		IF(tms.test_method = "Culture%",1,0) culture, 
		test_status,
		test_result_binary,
		test_result,
		test_result_text,hospital,hospital.logo,hospital.place,district,state,test_area,provisional_diagnosis,
		IF(micro_organism_test.micro_organism_test_id!="",GROUP_CONCAT(DISTINCT CONCAT(micro_organism_test.micro_organism_test_id,",",micro_organism,",",antibiotic),",",antibiotic_result,"^"),0) micro_organism_test,
		',false)
		->from('test_order')->join('test','test_order.order_id=test.order_id')->join('test_sample','test_order.order_id=test_sample.order_id')
		->join('test_group','test.group_id=test_group.group_id','left')
		->join('test_master as ts','test.test_master_id=ts.test_master_id','left')		
		->join('lab_unit lus','ts.numeric_result_unit=lus.lab_unit_id','left')
		->join('lab_unit lug','test_group.numeric_result_unit=lug.lab_unit_id','left')
		->join('test_method tms','ts.test_method_id=tms.test_method_id','left')
		->join('test_area tas','ts.test_area_id=tas.test_area_id','left')
		->join('micro_organism_test','test.test_id = micro_organism_test.test_id','left')
		->join('antibiotic_test','micro_organism_test.micro_organism_test_id = antibiotic_test.micro_organism_test_id','left')
		->join('antibiotic','antibiotic_test.antibiotic_id = antibiotic.antibiotic_id','left')
		->join('micro_organism','micro_organism_test.micro_organism_id = micro_organism.micro_organism_id','left')
		->join('patient_visit','test_order.visit_id = patient_visit.visit_id')
		->join('patient','patient_visit.patient_id = patient.patient_id')
		->join('department','patient_visit.department_id = department.department_id')
		->join('staff d_staff','department.lab_report_staff_id=d_staff.staff_id','left')
		->join('unit','patient_visit.unit=unit.unit_id','left')
		->join('staff u_staff','unit.lab_report_staff_id=u_staff.staff_id','left')
		->join('area','patient_visit.area=area.area_id','left')
		->join('staff a_staff','area.lab_report_staff_id=a_staff.staff_id','left')
		->join('department test_dept','tas.department_id=test_dept.department_id')
		->join('hospital','test_dept.hospital_id=hospital.hospital_id')
		->join('specimen_type','test_sample.specimen_type_id=specimen_type.specimen_type_id')
		->group_by('test_id');
		$this->db->where('test_order.order_id',$order_id);
		$query=$this->db->get();
		return $query->result();
	}		
	
	function upload_test_results(){
		$tests=$this->input->post('test');
		$data=array();
		$antibiotics_data=array();
		$this->db->trans_start();
		foreach($tests as $test){
			if($this->input->post('binary_result_'.$test)!=NULL || $this->input->post('numeric_result_'.$test)!=NULL || $this->input->post('text_result_'.$test)!=NULL){
				$binary_result=$this->input->post('binary_result_'.$test);
				$numeric_result=$this->input->post('numeric_result_'.$test);
				$text_result=$this->input->post('text_result_'.$test);
				$data[]=array(
					'test_id'=>$test,
					'test_result_binary'=>$binary_result,
					'test_result'=>$numeric_result,
					'test_result_text'=>$text_result,
					'test_date_time'=>date("Y-m-d H:i:s"),
					'test_status'=>1
				);
				
				if($binary_result == 1 && !!$this->input->post('micro_organisms_'.$test)){
					$micro_organisms = $this->input->post('micro_organisms_'.$test);
					$m=0;
					foreach($micro_organisms as $mo){
						$this->db->insert('micro_organism_test',array('test_id'=>$test,'micro_organism_id'=>$mo));
						$micro_organism_test_id = $this->db->insert_id();
						if(count($this->input->post('antibiotics_'.$test."_".$mo))>0){
							$antibiotics=$this->input->post('antibiotics_'.$test.'_'.$mo);
							$i=0;
							foreach($antibiotics as $ab){
								$antibiotics_data[] = array(
									'antibiotic_id'=>$this->input->post('antibiotics_'.$test.'_'.$mo.'_'.$i),
									'micro_organism_test_id'=>$micro_organism_test_id,
									'antibiotic_result'=>$this->input->post('antibiotic_results_'.$test.'_'.$mo.'_'.$i),
								);
							$i++;
							}
						}
						else{
							$this->db->trans_rollback();
							return false;
						}
						$m++;
					}
				}
			}
		}
		if(!!$antibiotics_data)
		$this->db->insert_batch('antibiotic_test',$antibiotics_data);
		$this->db->update_batch('test',$data,'test_id');
		$this->db->select('test_status')->from('test')->join('test_order','test.order_id=test_order.order_id')->where('test_order.order_id',$this->input->post('order_id'));
		$query=$this->db->get();
		$result=$query->result();
		$order_status=2;
		foreach($result as $row){
			if($row->test_status == 0) $order_status = 1;
		}
		if($order_status==2){
			$this->db->where('order_id',$this->input->post('order_id'));
			$this->db->update('test_order',array('order_status'=>$order_status));
		}
		$this->db->trans_complete();
		if($this->db->trans_status() === FALSE){
					$this->db->trans_rollback();
					return false;
		}
		else return true;			
	}
	
	function approve_results(){
		$this->db->trans_start();
		$userdata = $this->session->userdata('logged_in');
		foreach($this->input->post('test') as $test){
			$this->input->post('approve_test_'.$test)==1?$status=2:$status=3;
			$this->db->where('test_id',$test);
			$this->db->update('test',array('test_status'=>$status,'test_approved_by'=>$userdata['user_id'],'reported_date_time'=>date("Y-m-d H:i:s")));
		}
		$this->db->where('order_id',$this->input->post('order_id'));
		$this->db->update('test_order',array('order_status'=>2));
		$this->db->select('
		a_staff.staff_id a_id,a_staff.email a_email,a_staff.first_name a_first_name,a_staff.phone a_phone,
		u_staff.staff_id u_id,u_staff.email u_email,u_staff.first_name u_first_name,u_staff.phone u_phone,
		d_staff.staff_id d_id,d_staff.email d_email,d_staff.first_name d_first_name,d_staff.phone d_phone',false)
		->from('test_order')->join('patient_visit','test_order.visit_id = patient_visit.visit_id')
		->join('area','patient_visit.area = area.area_id','left')
		->join('staff a_staff','area.lab_report_staff_id = a_staff.staff_id','left')
		->join('unit','patient_visit.unit = unit.unit_id','left')
		->join('staff u_staff','unit.lab_report_staff_id = u_staff.staff_id','left')
		->join('department','patient_visit.department_id = department.department_id','left')
		->join('staff d_staff','department.lab_report_staff_id = d_staff.staff_id','left')
		->where('order_id',$this->input->post('order_id'));
		$query=$this->db->get();
		$this->db->trans_complete();
		if($this->db->trans_status() === FALSE){
			$this->db->trans_rollback();
			return false;
		}
		else{
			return $query->row();
		}	
	}
	
	function search_patients(){
		$this->db->select('first_name,last_name,hosp_file_no,patient.patient_id,age_years,age_months,age_days')
		->from('patient')
		->join('patient_visit','patient.patient_id = patient_visit.patient_id')
		->like('hosp_file_no',$this->input->post('query'),'after')
		->where('YEAR(admit_date)',$this->input->post('year'))
		->where('visit_type',$this->input->post('visit_type'));
		$query=$this->db->get();
		if($query->num_rows()>0){
		return $query->result_array();
		}
		else return false;
	}
}
?>