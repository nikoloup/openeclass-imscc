<?php

class Helper {

	/* creates a compressed zip file */
	static function Zip($source, $destination)
	{
	    if (!extension_loaded('zip') || !file_exists($source)) {
	        return false;
	    }
	
	    $zip = new ZipArchive();
	    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
	        return false;
	    }
	
	    $source = str_replace('\\', '/', realpath($source));
	
	    if (is_dir($source) === true)
	    {
	        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
	
	        foreach ($files as $file)
	        {
	            $file = str_replace('\\', '/', $file);
	
	            // Ignore "." and ".." folders
	            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
	                continue;
	
	            $file = realpath($file);
	
	            if (is_dir($file) === true)
	            {
	                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
	            }
	            else if (is_file($file) === true)
	            {
	                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
	            }
	        }
	    }
	    else if (is_file($source) === true)
	    {
	        $zip->addFromString(basename($source), file_get_contents($source));
	    }
	
	    return $zip->close();
	}	

	static function sanitizeFilename($filename) {
		//Conver to lowercase
		$clean = strtolower($filename);

		//Replace blanks with underscores
		$clean = str_replace(" ", "_", $clean);

		return $clean;

	}


	//Manifest helpers

	static function createLinkXML($link_url)
	{
		 $weblink = simplexml_load_file('./files/weblink.xml');
                 $weblink->title = $link_url['title'];
                 $weblink->url->addAttribute('href',$link_url['url']);
                 $weblink->url->addAttribute('target','_blank');
                 file_put_contents('./tmp/links/weblink_'.$link_url['id'].'.xml', $weblink->saveXML());
	}

	static function addIMSSection(&$manifest, &$currentSectionCounter, &$identifierCounter, $section_title)
	{
		$manifest->organizations->organization->item->addChild('item'); //Section Item
                $section = $manifest->organizations->organization->item->item[$currentSectionCounter];
                $section->addAttribute('identifier','I_'.$identifierCounter);
                $section->addChild('title',$section_title);
                $identifierCounter++;
	}

	static function addIMSItem(&$section, &$manifest, &$sectionCounter, &$identifierCounter, &$resourceCounter, $type, $data, $level)
	{
		//$level represents indentation
	
		if($level==0)
		{	//L0 Item
			$section->addChild('item');
	                $section->item[$sectionCounter]->addAttribute('identifier','I_'.$identifierCounter);
	                $identifierCounter++;
	                $section->item[$sectionCounter]->addAttribute('identifierref','I_'.$identifierCounter);
	                $section->item[$sectionCounter]->addChild('title',$data['title']);
		}
		else if($level==1)
		{
			//L0 Item
			$section->addChild('item');
                        $section->item[$sectionCounter]->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->addChild('title');
			//L1 Item
			$section->item[$sectionCounter]->addChild('item');
			$section->item[$sectionCounter]->item->addAttribute('identifier','I_'.$identifierCounter);
			$identifierCounter++;
			$section->item[$sectionCounter]->item->addAttribute('identifierref','I_'.$identifierCounter);
			$section->item[$sectionCounter]->item->addChild('title',$data['title']);	
		}
		else if($level==2)
		{
			//L0 Item
                        $section->addChild('item');
                        $section->item[$sectionCounter]->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->addChild('title');     
                        //L1 Item
                        $section->item[$sectionCounter]->addChild('item');
                        $section->item[$sectionCounter]->item->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->item->addChild('title');
			//L2 Item
			$section->item[$sectionCounter]->item->addChild('item');
			$section->item[$sectionCounter]->item->item->addAttribute('identifier','I_'.$identifierCounter);
			$identifierCounter++;
			$section->item[$sectionCounter]->item->item->addAttribute('identifierref','I_'.$identifierCounter);
			$section->item[$sectionCounter]->item->item->addChild('title',$data['title']);
		}

	
		if($type=='link')
		{
			$manifest->resources->addChild('resource');
			$resource = $manifest->resources->resource[$resourceCounter];
			$resource->addAttribute('identifier','I_'.$identifierCounter);
                        $resource->addAttribute('type','imswl_xmlv1p1');
                        $resource->addChild('file');
			$resource->file->addAttribute('href','links/weblink_'.$data['id'].'.xml');
			$identifierCounter++;
                        $resourceCounter++;
                        $sectionCounter++;
		}
		else if($type=='file')
		{
			$manifest->resources->addChild('resource');
			$resource = $manifest->resources->resource[$resourceCounter];
			$resource->addAttribute('identifier','I_'.$identifierCounter);
			$resource->addAttribute('type','webcontent');
			$resource->addChild('file');
			$resource->file->addAttribute('href','course_files/'.Helper::sanitizeFilename($data['filename']));
			$identifierCounter++;
                        $resourceCounter++;
                        $sectionCounter++;
		}
	}

	static function addIMSLabel(&$section, &$sectionCounter, &$identifierCounter, $name, $level)
	{
		if($level==0)
                {       //L0 Item
                        $section->addChild('item');
                        $section->item[$sectionCounter]->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->addChild('title',$name);
                }
                else if($level==1)
                {
                        //L0 Item
                        $section->addChild('item');
                        $section->item[$sectionCounter]->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->addChild('title');
                        //L1 Item
                        $section->item[$sectionCounter]->addChild('item');
                        $section->item[$sectionCounter]->item->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->item->addChild('title',$name);
                }
                else if($level==2)
                {
                        //L0 Item
                        $section->addChild('item');
                        $section->item[$sectionCounter]->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->addChild('title');
                        //L1 Item
                        $section->item[$sectionCounter]->addChild('item');
                        $section->item[$sectionCounter]->item->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->item->addChild('title');
                        //L2 Item
                        $section->item[$sectionCounter]->item->addChild('item');
                        $section->item[$sectionCounter]->item->item->addAttribute('identifier','I_'.$identifierCounter);
                        $identifierCounter++;
                        $section->item[$sectionCounter]->item->item->addChild('title',$name);
                }		
                $sectionCounter++;
	}
}
