<?php

namespace Igorw\CgiHttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Process\ProcessBuilder;

class CgiHttpKernel implements HttpKernelInterface
{
    private $rootDir;
    private $frontController;

    public function __construct($rootDir, $frontController = null)
    {
        $this->rootDir = $rootDir;
        $this->frontController = $frontController;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $filename = $this->frontController ?: ltrim($request->getPathInfo(), '/');

        if (!file_exists($this->rootDir.'/'.$filename)) {
            return new Response('The requested file could not be found.', 404);
        }

        $process = ProcessBuilder::create()
            ->add('php-cgi')
            ->add('-d expose_php=Off')
            ->add('-d cgi.force_redirect=Off')
            ->add($filename)
            ->setInput($request->getContent())
            ->setEnv('SCRIPT_FILENAME', $filename)
            ->setEnv('SCRIPT_NAME', $this->rootDir.'/'.$filename)
            ->setEnv('PATH_INFO', $request->getPathInfo())
            ->setEnv('QUERY_STRING', $request->getQueryString())
            ->setEnv('REQUEST_URI', $request->getRequestUri())
            ->setEnv('REQUEST_METHOD', $request->getMethod())
            ->setWorkingDirectory($this->rootDir)
            ->getProcess();

        $process->start();
        $process->wait();

        list($headerList, $body) = explode("\r\n\r\n", $process->getOutput());
        $headers = $this->getHeaderMap(explode("\r\n", $headerList));

        $status = $this->getStatusCode($headers);

        return new Response($body, $status, $headers);
    }

    public function getStatusCode(array $headers)
    {
        if (isset($headers['Status'])) {
            list($code) = explode(' ', $headers['Status']);
            return (int) $code;
        }

        return 200;
    }

    public function getHeaderMap(array $headerList)
    {
        $headerMap = array();
        foreach ($headerList as $item) {
            list($name, $value) = explode(': ', $item);
            $headerMap[$name] = $value;
        }
        return $headerMap;
    }
}
