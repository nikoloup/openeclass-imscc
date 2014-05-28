<?php

//This software converts course exports from the OpenEclass v3 LMS to Moodle v2.6 LMS

//TODO Use simplexml references to cleanup editing sections
//TODO Section Counter does not need to be global?
//TODO Fix folders
//TODO Add categories to link conversion
//TODO Use a function for manifest editing?


include('helper.php');

class ConvertCourse {
	
	private $course_id;
	private $outputName; //Name for export zip

	private $link; //Database connection
	private $host; //Server url with eclass installation

	private $course; //Array storing course information
	private $manifest; //IMS Manifest to be exported
	private $files; //Array storing files and folders

	private $identifierCounter; //Counter for identifier to be used next, starts at 1
	private $resourceCounter; //Counter for all resources
	private $currentSectionCounter; //Counter for current section being edited in manifest


	public function __construct($dbhost,$user,$pass,$dbname,$host){

		//Connect to database
		$this->link = mysqli_connect($dbhost,$user,$pass,$dbname) or die ('Error connecting to mysql');
		mysqli_set_charset($this->link, "utf8");
		$this->course = array();
		$this->host = $host;

		//Load manifest template
		$this->manifest = simplexml_load_file('./files/imsmanifest.xml');

		//Create temporary directory
		mkdir('tmp');

		//Initialize identifier and currentSection counter
		$this->identifierCounter = 1;
		$this->currentSectionCounter = 0;
		$this->resourceCounter = 0;
	}

	private function retrieveName(){
		//Course code and title
		$query = "SELECT cours_id,code,intitule FROM cours WHERE cours_id=".$this->course_id.";";

		$result = mysqli_query($this->link,$query);
		$data = $result->fetch_assoc();

		//Check if course exists
		if(is_null($data))
		{
			echo 'Course with specified id does not exist';
			echo "\n";
			mysqli_close($this->link);
			exit;
		}

		$this->course['id'] = $this->course_id;
		$this->course['code'] = $data['code'];
		$this->course['title'] = $data['intitule'];
	}

	private function retrieveDescription(){
		//Course description
		$query = "SELECT unit_resources.title,unit_resources.comments,unit_resources.`order` FROM unit_resources
		INNER JOIN course_units ON course_units.id=unit_resources.`unit_id`
		INNER JOIN cours ON cours.`cours_id`=course_units.`course_id`
		WHERE cours.`cours_id`=".$this->course_id." ORDER BY unit_resources.`order`;";
		
		$result = mysqli_query($this->link,$query);
		while($data = $result->fetch_assoc())
		{
		        $info = array(
		                'title' => $data['title'],
		                'content' => $data['comments']
		        );
		        $this->course['course_information'][] = $info;
		}			
	}

	private function retrieveAnnouncements(){
		//Course announcements
		$query = "SELECT annonces.`title`,annonces.`contenu`,annonces.`preview`,annonces.`temps`,annonces.`ordre` FROM annonces WHERE annonces.`cours_id`=".$this->course_id." ORDER BY annonces.`ordre`;";
		$result = mysqli_query($this->link,$query);
		while($data = $result->fetch_assoc())
		{
		        $announcement = array(
		                'title' => $data['title'],
		                'content' => $data['contenu'],
		                'preview' => $data['preview'],
		                'date' => $data['temps']
		        );
		        $this->course['course_announcements'][] = $announcement;
		}		
	}

	private function retrieveLinks(){
		//Course link categories
		$query = "SELECT link_category.`name`,description,link_category.order,link_category.`id` FROM link_category WHERE link_category.`course_id`=".$this->course_id." ORDER BY link_category.order;";

		$result = mysqli_query($this->link,$query);
		while($data = $result->fetch_assoc())
		{
		        $category = array(
		                'id' => $data['id'],
		                'name' => $data['name'],
		                'description' => $data['description']
		        );
		        $this->course['course_link_categories'][] = $category;
		}

		$query = "SELECT link.`id`,link.`title`, link.`url`, link.`description`, link.`category`, link.`order` FROM link WHERE link.`course_id`=".$this->course_id." ORDER BY link.`category`, link.`order`;";
		
		//Course links
		$result = mysqli_query($this->link, $query);
		while($data = $result->fetch_assoc())
		{
			$link_url = array(
				'id' => $data['id'],
				'title' => $data['title'],
				'url' => $data['url'],
				'description' => $data['description'],
				'category' => $data['category']
			);
			$this->course['course_links'][] = $link_url;
		}
	}

	private function convertFiles(){
		//Recursively download all files in course and place in logical subfolders

		//Create folder in tmp for files
		mkdir('./tmp/course_files');
		$sectionCounter = 0;
	
		//Add section to manifest
		Helper::addIMSSection($this->manifest, $this->currentSectionCounter, $this->identifierCounter, 'Documents');

		$section = $this->manifest->organizations->organization->item->item[$this->currentSectionCounter];
		
		//Find all folders and documents
		$query = "SELECT document.`path`,document.`filename`,document.`format`,document.`title` FROM document WHERE document.`course_id`=".$this->course_id." ORDER BY path;";
		$result = mysqli_query($this->link,$query);
		
		while($data = $result->fetch_assoc())
		{
			//Find item level
			$level = substr_count($data['path'], '/') - 1;

			//If it is a file
			if($data['format']!='.dir')
			{
				$s_filename = Helper::sanitizeFilename($data['filename']);
	                        file_put_contents('./tmp/course_files/'.$s_filename, fopen($this->host.'/courses/'.$this->course['code'].'/document'.$data['path'], 'r'));
				Helper::addIMSItem($section, $this->manifest, $sectionCounter, $this->identifierCounter, $this->resourceCounter, 'file', $data, $level); 
			}
			//If it is a directory
			else if($data['format']=='.dir')
			{
				Helper::addIMSLabel($section, $sectionCounter, $this->identifierCounter, $data['filename'], $level);
			}
		}

		//Proceed to next section
		$this->currentSectionCounter++;
	}

	private function convertName(){
		//Fill in title
		$this->manifest->metadata->children('lomimscc',true)->lom->general->title->string = $this->course['title'];
	}

	private function convertDescription(){		
		//Fill in description
		$description_html = '';
		
		foreach($this->course['course_information'] as $section)
		{
		        $description_html = $description_html.'<h3>'.$section['title'].'</h3><p>'.$section['content'].'</p>';
		}
		
		$this->manifest->metadata->children('lomimscc',true)->lom->general->description->string = $description_html;
	}

	private function convertLinks(){
		//If there are links
		if(!isset($this->course['course_links']))
			return;
		
		//Create folder in tmp for links
                mkdir('./tmp/links');
		$sectionCounter = 0;

		//Add section to manifest
		Helper::addIMSSection($this->manifest, $this->currentSectionCounter, $this->identifierCounter, 'Links');

                $section = $this->manifest->organizations->organization->item->item[$this->currentSectionCounter];
           
		//First add all general links
		
		foreach($this->course['course_links'] as $link_url)
		{
			if(intval($link_url['category'])==0)
			{
				//Create the xml
				Helper::createLinkXML($link_url);				
	
				//Edit the manifest
				Helper::addIMSItem($section, $this->manifest, $sectionCounter, $this->identifierCounter, $this->resourceCounter, 'link', $link_url, 0);				
			}
		}
		foreach($this->course['course_link_categories'] as $cat)
		{
			//Add the label to the manifest
			Helper::addIMSLabel($section, $sectionCounter, $this->identifierCounter, $cat['name'], 0);

			//Add links below the label
			foreach($this->course['course_links'] as $link_url)
			{
				if(intval($link_url['category'])==$cat['id'])
				{
					//Create the xml
	                                Helper::createLinkXML($link_url); 
					
                               		//Edit the manifest
					Helper::addIMSItem($section, $this->manifest, $sectionCounter, $this->identifierCounter, $this->resourceCounter, 'link', $link_url, 1);
                		}
			}
		}

		//Proceed to next section
		$this->currentSectionCounter++;
			
		
	}

	private function createZip(){
		//Write xml output
		file_put_contents('./tmp/imsmanifest.xml' , $this->manifest->saveXML());	

		$result = Helper::Zip('./tmp','./output/'.$this->outputName);
		if(!$result)
			echo 'Error: Zip creation failed';
		
		//Close database link
		mysqli_close($this->link);		

	}

	private function retrieveAll(){
                $this->retrieveName();
                $this->retrieveDescription();
                $this->retrieveAnnouncements();
                $this->retrieveLinks();
        }

        private function convertAll(){
                $this->convertName();
                $this->convertDescription();
		$this->convertFiles();
		$this->convertLinks();
        }

	public function convert($course_id, $outputName){
                $this->course_id = $course_id;
                $this->outputName = $outputName;
                $this->retrieveAll();
                $this->convertAll();
                $this->createZip();
        }	

}



?>

