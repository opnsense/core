<?php
/**
 * Class for preprocessing callgrind files.
 * 
 * Information from the callgrind file is extracted and written in a binary format for 
 * fast random access.
 * 
 * @see http://code.google.com/p/webgrind/wiki/PreprocessedFormat
 * @see http://valgrind.org/docs/manual/cl-format.html
 * @package Webgrind
 * @author Jacob Oettinger
 **/
class Webgrind_Preprocessor
{

	/**
	 * Fileformat version. Embedded in the output for parsers to use.
	 */
	const FILE_FORMAT_VERSION = 7;

	/**
	 * Binary number format used.
	 * @see http://php.net/pack
	 */
	const NR_FORMAT = 'V';

	/**
	 * Size, in bytes, of the above number format
	 */
	const NR_SIZE = 4;

	/**
	 * String name of main function
	 */
	const ENTRY_POINT = '{main}';


	/**
	 * Extract information from $inFile and store in preprocessed form in $outFile
	 *
	 * @param string $inFile Callgrind file to read
	 * @param string $outFile File to write preprocessed data to
	 * @return void
	 **/
	static function parse($inFile, $outFile)
	{
		$in = @fopen($inFile, 'rb');
		if(!$in)
			throw new Exception('Could not open '.$inFile.' for reading.');
		$out = @fopen($outFile, 'w+b');
		if(!$out)
			throw new Exception('Could not open '.$outFile.' for writing.');
		
		$nextFuncNr = 0;
		$functions = array();
		$headers = array();
		$calls = array();
		
		
		// Read information into memory
		while(($line = fgets($in))){
			if(substr($line,0,3)==='fl='){
				// Found invocation of function. Read functionname
				list($function) = fscanf($in,"fn=%[^\n\r]s");
				if(!isset($functions[$function])){
					$functions[$function] = array(
                        'filename'              => substr(trim($line),3), 
                        'invocationCount'       => 0,
                        'nr'                    => $nextFuncNr++,
                        'count'                 => 0,
                        'summedSelfCost'        => 0,
                        'summedInclusiveCost'   => 0,
                        'calledFromInformation' => array(),
                        'subCallInformation'    => array()
					);
				} 
				$functions[$function]['invocationCount']++;
				// Special case for ENTRY_POINT - it contains summary header
				if(self::ENTRY_POINT == $function){
					fgets($in);				
					$headers[] = fgets($in);
					fgets($in);
				}
				// Cost line
				list($lnr, $cost) = fscanf($in,"%d %d");
				$functions[$function]['line'] = $lnr;
				$functions[$function]['summedSelfCost'] += $cost;
				$functions[$function]['summedInclusiveCost'] += $cost;				
			} else if(substr($line,0,4)==='cfn=') {
				
				// Found call to function. ($function should contain function call originates from)
				$calledFunctionName = substr(trim($line),4);
				// Skip call line
				fgets($in);
				// Cost line
				list($lnr, $cost) = fscanf($in,"%d %d");
				
				$functions[$function]['summedInclusiveCost'] += $cost;
				
				if(!isset($functions[$calledFunctionName]['calledFromInformation'][$function.':'.$lnr]))
					$functions[$calledFunctionName]['calledFromInformation'][$function.':'.$lnr] = array('functionNr'=>$functions[$function]['nr'],'line'=>$lnr,'callCount'=>0,'summedCallCost'=>0);
				
				$functions[$calledFunctionName]['calledFromInformation'][$function.':'.$lnr]['callCount']++;
				$functions[$calledFunctionName]['calledFromInformation'][$function.':'.$lnr]['summedCallCost'] += $cost;

				if(!isset($functions[$function]['subCallInformation'][$calledFunctionName.':'.$lnr])){
					$functions[$function]['subCallInformation'][$calledFunctionName.':'.$lnr] = array('functionNr'=>$functions[$calledFunctionName]['nr'],'line'=>$lnr,'callCount'=>0,'summedCallCost'=>0);
				}
				
				$functions[$function]['subCallInformation'][$calledFunctionName.':'.$lnr]['callCount']++;
				$functions[$function]['subCallInformation'][$calledFunctionName.':'.$lnr]['summedCallCost'] += $cost;
				
				
			} else if(strpos($line,': ')!==false){
				// Found header
				$headers[] = $line;
			}
		}
			
				
		// Write output
		$functionCount = sizeof($functions);
		fwrite($out, pack(self::NR_FORMAT.'*', self::FILE_FORMAT_VERSION, 0, $functionCount));
		// Make room for function addresses
		fseek($out,self::NR_SIZE*$functionCount, SEEK_CUR);
		$functionAddresses = array();
		foreach($functions as $functionName => $function){
			$functionAddresses[] = ftell($out);
			$calledFromCount = sizeof($function['calledFromInformation']);
			$subCallCount = sizeof($function['subCallInformation']);
			fwrite($out, pack(self::NR_FORMAT.'*', $function['line'], $function['summedSelfCost'], $function['summedInclusiveCost'], $function['invocationCount'], $calledFromCount, $subCallCount));
			// Write called from information
			foreach((array)$function['calledFromInformation'] as $call){
				fwrite($out, pack(self::NR_FORMAT.'*', $call['functionNr'], $call['line'], $call['callCount'], $call['summedCallCost']));
			}
			// Write sub call information
			foreach((array)$function['subCallInformation'] as $call){
				fwrite($out, pack(self::NR_FORMAT.'*', $call['functionNr'], $call['line'], $call['callCount'], $call['summedCallCost']));
			}
			
			fwrite($out, $function['filename']."\n".$functionName."\n");
		}
		$headersPos = ftell($out);
		// Write headers
		foreach($headers as $header){
			fwrite($out,$header);
		}
		
		// Write addresses
		fseek($out,self::NR_SIZE, SEEK_SET);
		fwrite($out, pack(self::NR_FORMAT, $headersPos));
		// Skip function count
		fseek($out,self::NR_SIZE, SEEK_CUR);
		// Write function addresses
		foreach($functionAddresses as $address){
			fwrite($out, pack(self::NR_FORMAT, $address));			
		}
		
	}
	
}
