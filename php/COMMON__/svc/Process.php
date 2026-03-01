<?php
namespace COMMON__\svc;


# https://www.php.net/manual/en/function.exec.php#88704

/**
 * An easy way to keep in track of external processes.  
 * Ever wanted to execute a process in php, but you still wanted to have somewhat controll of the process ? Well.. This is a way of doing it.
 * @compability: Linux only. (Windows does not work).
 * @author: Peec
 */
class Process
{

    private string $command;
    private int $pid;
    #TODO add start_date to handle PID recycling


    public function __construct (?string $command=null)
    {
        if (!empty($command)) {
            $this->command = $command;
            $this->runCom ();
        }
    }


    private function runCom () : void
    {
        $command = "setsid nohup sh -c '{$this->command}' > /dev/null 2>&1 & echo $!"; # setsid : run in same pgroup, so that pid = pgid
        #TODO see if we can remove nohup and those redirections
        exec ($command ,$op);
        $this->pid = (int) $op [0];
    }


    public function setPid (int $pid) : void
    {
        $this->pid = $pid;
    }


    public function getPid () : int
    {
        return $this->pid;
    }


	/**
	 * check if the PID is running
	 */
    public function status () : bool
    {
        $command = "ps -p {$this->pid}";
        exec ($command,$op);
        if (!isset ($op [1]))
            return false;
        else
            return true;
    }


    public function start () : bool
    {
        if (!empty($this->command)) {
            $this->runCom();
            return true;
        }
        else
            return false;
    }


    public function stop () : bool
    {
		$status = $this->status();
        $command = "kill -KILL -{$this->pid} 2>&1"; # -PID to kill the whole PGID group
        exec ($command, $output, $result_code);
        var_dump($result_code, $output);
		
        if ($status === false) {
            return false; # not kill as not running
		}
        else {
            return ($result_code === 0); # kill command failed
		}
    }

}
