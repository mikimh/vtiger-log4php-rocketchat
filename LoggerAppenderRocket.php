<?php
/**
 * log4php is a PHP port of the log4j java logging package.
 * 
 * <p>This framework is based on log4j (see {@link http://jakarta.apache.org/log4j log4j} for details).</p>
 * <p>Design, strategies and part of the methods documentation are developed by log4j team 
 * (Ceki G�lc� as log4j project founder and 
 * {@link http://jakarta.apache.org/log4j/docs/contributors.html contributors}).</p>
 *
 * <p>PHP port, extensions and modifications by VxR. All rights reserved.<br>
 * For more information, please see {@link http://www.vxr.it/log4php/}.</p>
 *
 * <p>This software is published under the terms of the LGPL License
 * a copy of which has been included with this distribution in the LICENSE file.</p>
 * 
 * @package log4php
 * @subpackage appenders
 */
require_once("libraries/HTTP_Session2/HTTP/Session2.php");

/**
 * @ignore 
 */
if (!defined('LOG4PHP_DIR')) define('LOG4PHP_DIR', dirname(__FILE__) . '/..');

/**
 */
require_once(LOG4PHP_DIR . '/LoggerAppenderSkeleton.php');
require_once(LOG4PHP_DIR . '/LoggerLog.php');

/**
 * Log events to an email address. It will be created an email for each event. 
 *
 * <p>Parameters are 
 * {@link $smtpHost} (optional), 
 * {@link $port} (optional), 
 * {@link $from} (optional), 
 * {@link $to}, 
 * {@link $subject} (optional).</p>
 * <p>A layout is required.</p>
 *
 * @author Domenico Lordi <lordi@interfree.it>
 * @author VxR <vxr@vxr.it>
 * @version $Revision: 1.10 $
 * @package log4php
 * @subpackage appenders
 */
class LoggerAppenderRocket extends LoggerAppenderSkeleton {

    /**
     * @var string 'subject' field
     */
    var $subject        = '';

    /**
     * @access private
     */
    var $requiresLayout = true;

    /**
     * Constructor.
     *
     * @param string $name appender name
     */
    function LoggerAppenderRocket($name)
    {
        $this->LoggerAppenderSkeleton($name);
    }

    function activateOptions()
    { 
        $this->closed = false;
    }
    
    function close()
    {
        $this->closed = true;
    }

	function setSubject($subject)       { $this->subject = $subject; }

    function getSubject()   { return $this->subject; }
	
    function append($event){
		global $current_user;
		
		include_once 'include/Webservices/Utils.php';
		
        $message = new \stdClass();
        $message->username = $this->getSubject();
		$message->icon_emoji = ':robot:'; 
        $message->text = $this->layout->format($event);
		
		$rows = debug_backtrace(2,6); 
		$backtrace = end($rows); 
		
        $message->attachments = [array(
            'title' => 'User',
            'color'=> '#764FA5', 
			'collapsed' => true, 
			'title_link' => $GLOBALS['site_URL']."index.php?module=CompanyUsers&parent=Tools&view=Detail&record=".$GLOBALS['current_user']->id,	
			'fields' => [
				[
				'title' => 'username',
				'value' => !empty($current_user->user_name) ? 
					$current_user->user_name : $GLOBALS['current_user']->user_name ], 
				['title' => 'id',
				'value' => !empty($GLOBALS['current_user']->id) ? 
					$GLOBALS['current_user']->id : HTTP_Session2::get('authenticatedUserId')], 
				['title' => 'roleid',
				'value' => !empty($current_user->roleid) ? 
					$current_user->roleid : $GLOBALS['current_user']->roleid ],
			]  
        ),array(
            'title' => 'App',
            'color'=> '#764FA2', 
			'collapsed' => true, 
			'fields' => [
				['title' => 'Current module', 
				 'value' => $GLOBALS['currentModule']],
				['title' => 'View', 
				 'value' => $_REQUEST['view']],
				['title' => 'php_errormsg', 
				 'value' => $GLOBALS['php_errormsg']],
				['title' => 'Language', 
				 'value' => $GLOBALS['current_language']],
				['title' => 'Referer', 
				 'value' => $_SERVER['HTTP_REFERER']],	
				['title' => 'URL', 
				 'value' => $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']], 
				['title' => 'File', 
				 'value' => $backtrace['file']], 
				['title' => 'Line',
				 'value' => $backtrace['line']]
			]
        )];
		
		$backtrace = debug_backtrace(2,15); 
		foreach ($backtrace as $row) { 
			if ( strpos($row['file'], 'libraries/log4php.debug') === false ) { 
				$backtraceAttachment[] = [
					'title' => str_replace($GLOBALS['root_directory'], '',$row['file']) , 
					'value' => ' line('.$row['line'].')'.' function('.$row['function'].')'
									.' args('.implode(', ', $row['args']).')' 
				]; 
			}
		}
		
		$message->attachments[] = [
				'title' => 'Backtrace',
				'color'=> '#764FA2', 
				'collapsed' => true, 
				'fields' => $backtraceAttachment]; 
		
		$operation = vtws_getParameter($_REQUEST, "operation");
		
		if (!empty($operation)){ 
			// ------------------------
			$value = ''; 
    
			if (!empty($_REQUEST['elementType']) ) { 
				$value .= 'Module: '. $_REQUEST['elementType'].' ';  
			}
			if ( !empty($_REQUEST['element']) ) { 

				if ( is_array( $_REQUEST['element'] ) ){ 
					$value .= print_r($_REQUEST['element'], True).' ';
				}else {
					$value .= $_REQUEST['element'].' ';
				}
			} else if ( !empty($_REQUEST['query']) ) { 
				$value .= $_REQUEST['query']; 
			}
			
			$message->attachments[] = array(
				'title' => 'Webservice',
				'color'=> '#764FA2', 
				'collapsed' => true, 
				'fields' => [
					['title' => 'Operation', 
					 'value' => $operation],
					['title' => 'Query', 
					 'value' => $value]
				]
			);
		}
		
		if ( !empty($GLOBALS['sql_errors_log']) ) { 
			
			foreach($GLOBALS['sql_errors_log'] as $error){ 
				$fields[] = array( 'title' => 'Error', 
								   'value' => print_r($error, true));  
			}
			
			$message->attachments[] = array(
				'title' => 'Database',
				'color'=> '#764FA2', 
				'collapsed' => true, 
				'fields' => $fields 
			);  
		}
		
		
		// -------------------------------
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $GLOBALS['rocketchat_URL'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER =>( ($GLOBALS['production'] == TRUE) ? true : false),
            CURLOPT_SSL_VERIFYHOST => ( ($GLOBALS['production'] == TRUE) ? 2 : 0),
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_HTTPHEADER => array(
                "Cache-Control: no-cache",
                "Content-Type: application/json",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
    }
}
?>
