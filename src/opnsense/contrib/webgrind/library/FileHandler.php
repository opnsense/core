<?php
require 'Reader.php';
require 'Preprocessor.php';

/**
 * Class handling access to data-files(original and preprocessed) for webgrind.
 * @author Jacob Oettinger
 * @author Joakim Nygård
 */
class Webgrind_FileHandler{
	
	private static $singleton = null;
	
	
	/**
	 * @return Singleton instance of the filehandler
	 */
	public static function getInstance(){
		if(self::$singleton==null)
			self::$singleton = new self();
		return self::$singleton;
	}
		
	private function __construct(){
		// Get list of files matching the defined format
		$files = $this->getFiles(Webgrind_Config::xdebugOutputFormat(), Webgrind_Config::xdebugOutputDir());
		
		// Get list of preprocessed files
        $prepFiles = $this->getPrepFiles('/\\'.Webgrind_Config::$preprocessedSuffix.'$/', Webgrind_Config::storageDir());
		// Loop over the preprocessed files.		
		foreach($prepFiles as $fileName=>$prepFile){
			$fileName = str_replace(Webgrind_Config::$preprocessedSuffix,'',$fileName);
			
			// If it is older than its corrosponding original: delete it.
			// If it's original does not exist: delete it
			if(!isset($files[$fileName]) || $files[$fileName]['mtime']>$prepFile['mtime'] )
				unlink($prepFile['absoluteFilename']);
			else
				$files[$fileName]['preprocessed'] = true;
		}
		// Sort by mtime
		uasort($files,array($this,'mtimeCmp'));
		
		$this->files = $files;
	}
	
	/**
	 * Get the value of the cmd header in $file
	 *
	 * @return void string
	 */	
	private function getInvokeUrl($file){
	    if (preg_match('/.webgrind$/', $file)) 
	        return 'Webgrind internal';

		// Grab name of invoked file. 
	    $fp = fopen($file, 'r');
        $invokeUrl = '';
        while ((($line = fgets($fp)) !== FALSE) && !strlen($invokeUrl)){
            if (preg_match('/^cmd: (.*)$/', $line, $parts)){
                $invokeUrl = isset($parts[1]) ? $parts[1] : '';
            }
        }
        fclose($fp);
        if (!strlen($invokeUrl)) 
            $invokeUrl = 'Unknown!';

		return $invokeUrl;
	}
	
	/**
	 * List of files in $dir whose filename has the format $format
	 *
	 * @return array Files
	 */
	private function getFiles($format, $dir){
		$list = preg_grep($format,scandir($dir));
		$files = array();
		
		$scriptFilename = $_SERVER['SCRIPT_FILENAME'];
		
		# Moved this out of loop to run faster
		if (function_exists('xdebug_get_profiler_filename'))
		    $selfFile = realpath(xdebug_get_profiler_filename());
		else 
		    $selfFile = '';
		
		foreach($list as $file){
			$absoluteFilename = $dir.$file;

			// Exclude webgrind preprocessed files
			if (false !== strstr($absoluteFilename, Webgrind_Config::$preprocessedSuffix))
			    continue;
			
			// Make sure that script never parses the profile currently being generated. (infinite loop)
			if ($selfFile == realpath($absoluteFilename))
				continue;
				
			$invokeUrl = rtrim($this->getInvokeUrl($absoluteFilename));
			if (Webgrind_Config::$hideWebgrindProfiles && $invokeUrl == dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'index.php')
			    continue;
			

			$files[$file] = array('absoluteFilename' => $absoluteFilename, 
			                      'mtime' => filemtime($absoluteFilename), 
			                      'preprocessed' => false, 
			                      'invokeUrl' => $invokeUrl,
			                      'filesize' => $this->bytestostring(filesize($absoluteFilename))
			                );
		}		
		return $files;
	}
	
	/**
	 * List of files in $dir whose filename has the format $format
	 *
	 * @return array Files
	 */
	private function getPrepFiles($format, $dir){
		$list = preg_grep($format,scandir($dir));
		$files = array();
		
		$scriptFilename = $_SERVER['SCRIPT_FILENAME'];
		
		foreach($list as $file){
			$absoluteFilename = $dir.$file;
			
			// Make sure that script does not include the profile currently being generated. (infinite loop)
			if (function_exists('xdebug_get_profiler_filename') && realpath(xdebug_get_profiler_filename())==realpath($absoluteFilename))
				continue;
				
			$files[$file] = array('absoluteFilename' => $absoluteFilename, 
			                      'mtime' => filemtime($absoluteFilename), 
			                      'preprocessed' => true, 
			                      'filesize' => $this->bytestostring(filesize($absoluteFilename))
			                );
		}		
		return $files;
	}
	/**
	 * Get list of available trace files. Optionally including traces of the webgrind script it self
	 *
	 * @return array Files
	 */
	public function getTraceList(){
		$result = array();
		foreach($this->files as $fileName=>$file){
			$result[] = array('filename'  => $fileName, 
			                  'invokeUrl' => str_replace($_SERVER['DOCUMENT_ROOT'].'/', '', $file['invokeUrl']),
			                  'filesize'  => $file['filesize'],
			                  'mtime'     => date(Webgrind_Config::$dateFormat, $file['mtime'])
			            );
		}
		return $result;
	}
	
	/**
	 * Get a trace reader for the specific file.
	 * 
	 * If the file has not been preprocessed yet this will be done first.
	 *
	 * @param string File to read
	 * @param Cost format for the reader
	 * @return Webgrind_Reader Reader for $file
	 */
	public function getTraceReader($file, $costFormat){
		$prepFile = Webgrind_Config::storageDir().$file.Webgrind_Config::$preprocessedSuffix;
		try{
			$r = new Webgrind_Reader($prepFile, $costFormat);
		} catch (Exception $e){
			// Preprocessed file does not exist or other error
			Webgrind_Preprocessor::parse(Webgrind_Config::xdebugOutputDir().$file, $prepFile);
			$r = new Webgrind_Reader($prepFile, $costFormat);
		}
		return $r;
	}
	
	/**
	 * Comparison function for sorting
	 *
	 * @return boolean
	 */
	private function mtimeCmp($a, $b){
		if ($a['mtime'] == $b['mtime'])
		    return 0;

		return ($a['mtime'] > $b['mtime']) ? -1 : 1;
	}
	
	/**
	 * Present a size (in bytes) as a human-readable value
	 *
	 * @param int    $size        size (in bytes)
	 * @param int    $precision    number of digits after the decimal point
	 * @return string
	 */
	private function bytestostring($size, $precision = 0) {
   		$sizes = array('YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'KB', 'B');
		$total = count($sizes);

		while($total-- && $size > 1024) {
		    $size /= 1024;
		}
		return round($size, $precision).$sizes[$total];
    }
}