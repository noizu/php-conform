<?php

namespace NoizuLabs\PHPConform\PHPUnitExtension;

if(ENABLE_UTIL) PHPUnit_Util_Filter::addFileToFilter(__FILE__, 'PHPUNIT');
require_once(dirname(__FILE__) . '/Module.php');

class BDDPrinter extends Module
{
	/* General */
	protected $initialSpace = 0;
	protected $defaultSpaceChar = ' ';
	protected $defaultStatusColor = 'dark_gray';
	protected $defaultSpacing = 2;

	/* Title Bar */
	protected $defaultTitleBarChar = '*';
	protected $minimumTitleBarLength = 50;
	protected $defaultTitleBarTopRows = "1";
	protected $defaultTitleBarBotRows = "1";
	protected $defaultTitleBarLeftStars = "2";
	protected $defaultTitleBarRightStars = "2";
	protected $defaultTitleBarLeftSpace = "1";
	protected $defaultTitleBarRightSpace = "3";
         
        protected $incomplete = 0; 
        protected $successful = 0;
        protected $failed = 0; 
        protected $skipped = 0; 

    public $enableColors = true;
    protected $_noizuDirectWrite = true;

    public function addError(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {

    }

    public function addFailure(\PHPUnit_Framework_Test $test, \PHPUnit_Framework_AssertionFailedError $e, $time)
    {

    }

    public function addIncompleteTest(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {

    }

    public function addSkippedTest(\PHPUnit_Framework_Test $test, \Exception $e, $time)
    {

    }

    public function startTest(\PHPUnit_Framework_Test $test)
    {

    }


    /**
     *  @todo Improve this logic to account for skipper, incomplete, assertFailed and actual Exception failures
     */
    protected function getStoryDetails(\PHPUnit_Framework_Test $test, $time)
    {
    	$details = array();
    	$details['title'] = isset($test->name) ? trim($test->name) : "";
    	$details['status'] =  $test->currentScenario['status'];
    	$details['numAsserts'] = $test->numAsserts;
    	if (isset($test->currentScenario['steps'])) {
	    	// Steps
    		$details['steps'] = array();
    		foreach ($test->currentScenario['steps'] as $step) {
    			$stepDetails = array();
    			$stepDetails['status'] = $step['status']['result'];
				$stepDetails['step'] = $step['step'];
				if($step['status']['result'] == "Multi") {
					// Override Provided Status (as it is incorrect);
					$details['status'] = "Multi";
				}
				if (isset($step['status']['exception']) && $step['status']['exception'] instanceof \Exception) {
					$stepDetails['exception'] = $step['status']['exception']->getMessage();
				}
				$details['steps'][] = $stepDetails;
    		}
    	}

    	if (isset($test->currentScenario['multi'])) {
    		// Grab Example Data
    		$examples = $test->currentScenario['examples'];

    		// Grab Headers
    		$headers = array_keys($examples);
    		$entries = array();
    		foreach($headers as $header) {
    			$entries[$header] = array();
    		}

    		// Grab Depth
    		$depth = count($examples[$headers[0]]);

    		// Load Example Data + Status
    		for ($i = 0; $i < $depth; $i++) {
    			foreach ($headers as $header) {
					$entries[$header][$i] = array();
					$entries[$header][$i]['value'] = $test->currentScenario['examples'][$header][$i];
					$entries[$header][$i]['status'] = $test->currentScenario['multi-steps']['status'][$i][$header];
    			}
    		}

    		// Load Test Failures
    		if(!empty($test->currentScenario['multi-steps']['exception'])) {
    			$failures = array();
    			for ($i = 0; $i < $depth; $i++) {
    				if (isset($test->currentScenario['multi-steps']['exception'][$i]) && $test->currentScenario['multi-steps']['exception'][$i] instanceof \Exception){
    					$failure = array();
    					$failure['entry'] = $i;
						$failure['errorClass'] = get_class($test->currentScenario['multi-steps']['exception'][$i]);
    					$failure['msg'] =  $test->currentScenario['multi-steps']['exception'][$i]->getMessage();
    					if($failure['errorClass'] == "PHPUnit_Framework_IncompleteTestError") {
    						$failure['type'] = "Incomplete";
    					} else {
    						$failure['type'] = "Failed";
    					}
    					$failures[] = $failure;
    				}
    			}
    		}
    		$details['dataset'] = array();
    		$details['dataset']['headers'] = $headers;
    		$details['dataset']['depth'] = $depth;
    		$details['dataset']['entries'] = $entries;
    		$details['dataset']['failures'] = $failures;
    	}
    	return $details;
    }

    protected function getStepStatusPrefix($status)
    {
    	$bar = "";
    	switch ($status) {
    		case 'Passed':
    			$bar = "[+]";
    			break;
    		case 'Failed':
    			$bar = "[-]";
    			break;
    		case 'Pending':
    			$bar = "[P]";
    			break;
    		case 'Multi':
    			$bar = "[M]";
    			break;
    		case 'Abort':
    			$bar = "[A]";
    			break;

    		default:
    			$bar = "[?]";
    			break;
    	}
    	return $bar;
    }

    protected function getStepStatusColor($status)
    {
    	$bar = "";
    	switch ($status) {
    		case 'Passed':
    			$bar = 'light_green';
    			break;
    		case 'Failed':
    			$bar = 'light_red';
    			break;
    		case 'Pending':
    			$bar = 'yellow';
    			break;
    		case 'Multi':
    			$bar = 'light_blue';
    			break;
    		case 'Abort':
    		default:
    			$bar = 'light_gray';
    			break;
    	}
    	return $bar;
    }

    protected function getStatusColor($status)
    {
    	$bar = "";
    	switch ($status) {
    		case 'Passed':
    			$bar = 'green';
    			break;
    		case 'Failed':
    			$bar = 'red';
    			break;

    		case 'Incomplete':
    		case 'Pending':
    			$bar = 'yellow';
    			break;
    		case 'Multi':
    			$bar = 'light_blue';
    			break;
    		case 'Abort':
    		default:
    			$bar = $this->defaultStatusColor;
    			break;
    	}
    	return $bar;
    }

    protected function displayStoryTitleBar($offset, $title, $status)
    {
    	$status = trim($status);
    	$title = trim($title);

    	//=============================================
    	// ShortHand Variables
    	//=============================================
    	$sp = $this->defaultSpaceChar;
    	$bar = $this->defaultTitleBarChar;
    	$lst = $this->defaultTitleBarLeftStars;
    	$rst = $this->defaultTitleBarRightStars;
    	$ls = $this->defaultTitleBarLeftSpace;
    	$rs = $this->defaultTitleBarRightSpace;
    	$tr = $this->defaultTitleBarTopRows;
    	$br = $this->defaultTitleBarBotRows;

    	//=============================================
    	// Settings
    	//=============================================
    	$prefix = "Story: ";
    	$statusColor = $this->getStatusColor($status);
    	$barColor = "light_gray";
    	$displayStatus = " ($status)";
    	$displayStatusColor = $this->highlight($displayStatus, $statusColor);
    	$titleColor = $this->highlight($title, 'white');

    	//=============================================
    	//Calculations
    	//=============================================
    	$colorAdjustment = strlen($displayStatusColor) - strlen($displayStatus) + strlen($titleColor) - strlen($title);

    	$title_bar_ln =  max(
    							array(
    									$this->minimumTitleBarLength,
    									$lst +  $ls +  strlen($prefix) + strlen($title) + strlen($displayStatus) + $rs + $rst)
    							);
    	$centerLength = $title_bar_ln - ($lst + $ls + strlen($prefix) + $rs + $rst);
    	$centerLength += $colorAdjustment;

    	//=============================================
    	//Render
    	//=============================================

    	// Top Bar
    	for($i = 0; $i < $tr; $i++ ) {
    		echo str_repeat($sp, $offset) . $this->highlight( str_repeat($bar, $title_bar_ln) , $barColor) . "\n";
    	}

    	// Left Portion of Title Bar
    	echo str_repeat($sp, $offset) . $this->highlight( str_repeat($bar, $lst) , $barColor) . str_repeat($sp, $ls) . $prefix;

    	// Title
		echo $this->alignLeft( $titleColor . $displayStatusColor,  $centerLength );

    	// Right Portion of Title Bar
		echo str_repeat($sp, $rs) . $this->highlight( str_repeat($bar, $rst) , $barColor) . "\n";

    	// Bottom Bar
        	for($i = 0; $i < $br; $i++ ) {
    		echo str_repeat($sp, $offset) . $this->highlight( str_repeat($bar, $title_bar_ln) , $barColor) . "\n";
    	}
    }

    protected function displayStoryDetails($offset, $steps)
    {
    	$sp = $this->defaultSpaceChar;
    	echo "\n";
    	echo str_repeat($sp, $offset) . "Steps:\n";
    	$offset += $this->defaultSpacing;
    	foreach($steps as $step)
    	{
    		$stepTxt = $step['step'];
			$status = $step['status'];
    		$statusColor = $this->getStepStatusColor($status);
    		$statusSymbol = $this->getStepStatusPrefix($status);

    		$stepTxt = $this->highlight($statusSymbol . " " . $stepTxt,$statusColor);
    		echo str_repeat($sp, $offset) . $stepTxt . "\n";

    		if(isset($step['exception'])) {
    			$offset += $this->defaultSpacing;
    			$erc = $this->getStatusColor($status);
    			echo str_repeat($sp, $offset) . $status . ": " . $this->highlight( $step['exception'] , $erc ) . "\n";
    			$offset -= $this->defaultSpacing;
    		}

    	}
    }

    protected function displayStoryDataSet($offset, $dataset)
    {
    	$padding = " ";
    	$sp = $this->defaultSpaceChar;
    	echo "\n";
    	echo str_repeat($sp, $offset) . "DataSets:\n";
    	$offset += $this->defaultSpacing;

    	//=============================================
    	// Determine Lengths for each data field
    	//=============================================
    	$headerLen = array();
    	$contentLen = array();
    	foreach($dataset['headers'] as $header)
    	{
    		$displayHeader = $header;
    		$headerLen[$header] = strlen($displayHeader);
    		$contentLen[$header] = 0;
    		foreach($dataset['entries'][$header] as $entry) {
    			$headerLen[$header] = max($headerLen[$header], strlen($entry['value']) + strlen($padding));
    			$contentLen[$header] = max($contentLen[$header], strlen($entry['value']) + strlen($padding));
    		}
    	}
    	//=============================================
    	// Output Header Row
    	//=============================================
    	$leftPadding = strlen($dataset['depth']);

    	echo str_repeat($sp, $offset) . str_repeat($sp, $leftPadding) . "|";
    	foreach($dataset['headers'] as $header)
    	{
    		$displayHeader = $header;
    		$colorizedHeader = $this->highlight($displayHeader,'light_blue');
			$adj = strlen($colorizedHeader) - strlen($displayHeader);
			$w = $headerLen[$header] + $adj;
			echo $this->alignCenter($colorizedHeader, $w);
			echo "|";
    	}
    	echo "\n";

    	//=============================================
    	// Output Dataset
    	//=============================================
    	for($i = 0; $i < $dataset['depth']; $i++) {
    		echo str_repeat($sp, $offset) . $this->alignRight($i, $leftPadding) . "|";
    		foreach($dataset['headers'] as $header)
    		{
    			$field = $dataset['entries'][$header][$i]['value'];
    			$field = $field . $padding;
    			$fieldStatus = $dataset['entries'][$header][$i]['status'];
				$fieldColor = $this->getStepStatusColor($fieldStatus);
    			$colorizedField = $this->highlight($field,$fieldColor);

    			$adj = strlen($colorizedField) - strlen($field);
    			$digitLen = $contentLen[$header] + $adj;
    			$columnLen = $headerLen[$header] + $adj;
    			echo $this->alignCenter($this->alignRight($colorizedField, $digitLen) , $columnLen);
    			echo "|";
    		}
    		echo "\n";
    	}

    	$offset -= $this->defaultSpacing;

    	//=============================================
    	// Output Failures
    	//=============================================
    	if(!empty($dataset['failures']))
    	{
    		echo "\n";
    		echo str_repeat($sp, $offset) . "Failures:\n";
    		$offset += $this->defaultSpacing;
	    	foreach($dataset['failures'] as $failure)
	    	{
	    		$failureColor = $this->getStatusColor($failure['type']);
	    		$failureMsg = $failure['type'] . " - " . $failure['msg'] ;
	    		$colorizedMsg = $this->highlight( $failureMsg, $failureColor);
	    		echo str_repeat($sp, $offset) . "DataSet(" . $this->alignLeft($failure['entry'], $leftPadding) . "): $colorizedMsg \n";
	    	}
	    	$offset -= $this->defaultSpacing;
    	}
    }

    protected function displayScenarioDetails($offset, $details)
    {
    	$sp = $this->defaultSpaceChar;
    	echo "\n";
    	echo str_repeat($sp, $offset) . "Scenario Details:\n";
    	$offset += $this->defaultSpacing;
    	echo str_repeat($sp, $offset) . "Total Asserts:" . $details['numAsserts'] . "\n";
    	$offset -= $this->defaultSpacing;
    }

    protected function displayStory($details)
    {
    	// 0. Initial Space Offset
    	$offset = $this->initialSpace;
    	$spacing = $this->defaultSpacing;

    	// 1. Story Title Bar
    	$this->displayStoryTitleBar($offset, $details['title'], $details['status']);
     	$offset += $spacing;

     	// 2. Story Description
     	$this->displayStoryDetails($offset, $details['steps']);

     	// 3. Story Data Set (if present)
     	if(isset($details['dataset']))
     	{
     		$this->displayStoryDataset($offset, $details['dataset']);
     	}

     	// 4. Scenario Details
     	$this->displayScenarioDetails($offset, $details);

     	// 5. End of Test
     	echo "\n\n";
     	$offset -= $spacing;
    }

    /* @Todo Simplify this by seperating out presentation of data from gathering of data.  */
    public function endTest(\PHPUnit_Framework_Test $test, $time)
    {
    	$this->displayStory($this->getStoryDetails($test,$time));

        switch ($test->currentScenario['status']) {
            case 'Passed':
                $this->successful++;
                break;
            case 'Failed':
                $this->failed++;
                break;
            case 'Pending':
                $this->incomplete++;
                break;
            default:
                $this->skipped++;
                break;
        }
    }

    protected function alignLeft($text, $width)
    {
    	$padding = $width - strlen($text);
    	return $text . str_repeat(" ", $padding);
    }

    protected function alignRight($text, $width)
    {
    	$padding = $width - strlen($text);
    	return str_repeat(" ", $padding) . $text;
    }

    protected function alignCenter($text, $width)
    {
        $l = strlen($text);
        $left = ceil(($width - $l) / 2);
        $right = floor(($width - $l) / 2);

        if($left < 0 ) {
        	$right += $left;
        	$left = 0;
        }
        if($right < 0 ) {
        	$right = 0;
        }
        return str_repeat(" ", $left) . $text . str_repeat(" ", $right);
    }

    public function startTestSuite(\PHPUnit_Framework_TestSuite $test)
    {
        echo "Beginning User Story " . $test->getName() . "\n";
    }

    public function endTestSuite(\PHPUnit_Framework_TestSuite $test)
    {

    }

    public function highlight($text, $color, $bgcolor = null)
    {
        if ($this->enableColors) {
            if (isset($color)) {
                $text = $this->highlight_fg($text, $color);
            }
            if (isset($bgcolor)) {
                $text = $this->highlight_bg($text, $bgcolor);
            }
        }
        return $text;
    }

    public function highlight_bg($text, $color)
    {
        static $colorMap = array(
    'black' => "\033[40m",
    'red' => "\033[41m",
    'green' => "\033[42m",
    'yellow' => "\033[43m",
    'blue' => "\033[44m",
    'magenta' => "\033[45m",
    'cyan' => "\033[46m",
    'light_gray' => "\033[47m");
        return $colorMap[$color] . $text . "\033[0m";
    }

    public function highlight_fg($text, $color)
    {
        static $colorMap = array(
    'black' => "\033[0;30m",
    'dark_gray' => "\033[1;30m",
    'blue' => "\033[0;34m",
    'light_blue' => "\033[1;34m",
    'green' => "\033[0;32m",
    'light_green' => "\033[1;32m",
    'cyan' => "\033[0;36m",
    'light_cyan' => "\033[1;36m",
    'red' => "\033[0;31m",
    'light_red' => "\033[1;31m",
    'purple' => "\033[0;35m",
    'light_purple' => "\033[1;35m",
    'brown' => "\033[0;33m",
    'yellow' => "\033[1;33m",
    'light_gray' => "\033[0;37m",
    'white' => "\033[1;37m");
        return $colorMap[$color] . $text . "\033[0m";
    }

}
