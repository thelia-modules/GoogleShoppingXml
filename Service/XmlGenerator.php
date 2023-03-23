<?php

namespace GoogleShoppingXml\Service;

use Exception;
use Generator;
use GoogleShoppingXml\Service\GoogleModel\GoogleProductModel;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @class XmlGenerator
 */
class XmlGenerator
{
    /** @var string */
    protected $filePath;

    /** @var ProgressBar */
    protected $progressBar;

    /**
     * @param array $data
     * @param string $filePath
     * @param OutputInterface|null $output
     * @return void
     * @throws ReflectionException
     */
    public function export(array $data, string $filePath, OutputInterface $output = null): void
    {
        $this->filePath = $filePath;
        $this->progressBar = null;

        if ($output) {
            $this->progressBar = new ProgressBar($output);
        }

        $this->writeContent($data);
    }

    /**
     * @param array $dataCollection
     * @return void
     * @throws ReflectionException|Exception
     */
    public function writeContent(array $dataCollection): void
    {
        $this->generateHeader();

        if ($this->progressBar) {
            $this->progressBar->start();
        }

        foreach ($dataCollection as $name => $content) {
            if (false === $content instanceof Generator) {
                $this->write($this->generateXmlNode($name, $content));
                continue;
            }

            /* Consume generator */
            /** @var GoogleProductModel $googleProductModel */
            foreach ($content as $googleProductModel) {
                try {
                    $reflect = new ReflectionClass($googleProductModel);
                    $properties = $reflect->getProperties(ReflectionProperty::IS_PROTECTED);

                    $xml = "<$name>" . PHP_EOL;

                    foreach ($properties as $property) {
                        $property->setAccessible(true);

                        if (!$value = $property->getValue($googleProductModel)) {
                            continue;
                        }

                        $xml .= $this->generateXmlNode($property->getName(), $value, $googleProductModel->getSuffix(), false);
                    }

                    $xml .= "</$name>" . PHP_EOL;

                    $this->write($xml);

                    if ($this->progressBar) {
                        $this->progressBar->advance();
                    }

                } catch (Exception $ex) {
                    throw new Exception('Error on content product id = ' . $googleProductModel->getId() . ' error : ' . $ex->getMessage());
                }
            }
        }

        $this->generateFooter();
    }

    /**
     * @param string $content
     * @param string $mode
     * @return void
     * @throws Exception
     */
    protected function write(string $content, string $mode = 'a+'): void
    {
        if (!$file = fopen($this->filePath, $mode)) {
            throw new Exception("Can't read file : ($this->filePath)");
        }

        if (!fwrite($file, $content)) {
            throw new Exception("Can't write file ($file)");
        }

        fclose($file);
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function generateFooter(): void
    {
        $xml = '</channel>' . PHP_EOL;
        $xml .= '</rss>';

        $this->write($xml);
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function generateHeader(): void
    {
        $xml = '<?xml version="1.0"?>' . PHP_EOL;
        $xml .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . PHP_EOL;
        $xml .= '<channel>' . PHP_EOL;

        $this->write($xml);
    }

    /**
     * @param string $nodeTitle
     * @param array|string $nodeContent
     * @param $titlePrefix
     * @param bool $sanitize
     * @return string
     */
    protected function generateXmlNode(string $nodeTitle, $nodeContent, $titlePrefix = null, bool $sanitize = true): string
    {
        $nodeTitleComputed = $titlePrefix ? $titlePrefix . $nodeTitle : $nodeTitle;
        $nodeContentComputed = $sanitize ? $this->xmlSafeEncode($nodeContent) : $nodeContent;

        if (!is_array($nodeContentComputed)) {
            return "<$nodeTitleComputed>" . htmlspecialchars($nodeContentComputed) . "</$nodeTitleComputed>" . PHP_EOL;
        }

        $xml = '';

        foreach ($nodeContent as $title => $value) {
            if (!is_numeric($nodeTitle)) {
                $xml .= "<$nodeTitleComputed>" . PHP_EOL;
            }

            $xml .= $this->generateXmlNode($title, $value, $titlePrefix, $sanitize);

            if (!is_numeric($nodeTitle)) {
                $xml .= "</$nodeTitleComputed>" . PHP_EOL;
            }
        }

        return $xml;
    }

    /**
     * @param $str
     * @return string
     */
    protected function xmlSafeEncode($str): string
    {
        return htmlspecialchars(html_entity_decode(trim(strip_tags($str))), ENT_XML1);
    }
}