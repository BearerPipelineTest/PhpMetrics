<?php

namespace Hal\Report\Html;

use Hal\Application\Config\Config;
use Hal\Component\Output\Output;
use Hal\Metric\Consolidated;
use Hal\Metric\Group\Group;
use Hal\Metric\Metrics;
use Hal\Report\ReporterInterface;

/**
 * This class takes care about the global report in HTML of consolidated metrics.
 */
final class Reporter implements ReporterInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Output
     */
    private $output;

    /**
     * @var string
     */
    protected $templateDir;

    /**
     * @var Consolidated[]
     */
    private $consolidatedByGroups;

    /**
     * @var Group[]
     */
    private $groups = [];

    /**
     * @var string
     */
    private $currentGroup;

    /**
     * @var string
     */
    private $assetPath = '';

    /**
     * @param Config $config
     * @param Output $output
     */
    public function __construct(Config $config, Output $output)
    {
        $this->config = $config;
        $this->output = $output;
        $this->templateDir = __DIR__ . '/../../../../templates';
    }

    /**
     * {@inheritDoc}
     */
    public function generate(Metrics $metrics)
    {
        $logDir = $this->config->get('report-html');
        if (!$logDir) {
            return;
        }

        // consolidate

        /** @var Group[] $groups */
        $groups = $this->config->get('groups');
        $this->groups = $groups;
        $consolidatedGroups = [];
        foreach ($groups as $group) {
            $reducedMetricsByGroup = $group->reduceMetrics($metrics);
            $consolidatedGroups[$group->getName()] = new Consolidated($reducedMetricsByGroup);
        }
        $consolidated = new Consolidated($metrics);

        $files = glob($logDir . '/js/history-*.json');
        natsort($files);
        $history = array_map(static function ($filename) {
            return json_decode(file_get_contents($filename));
        }, $files);

        foreach (['js', 'css', 'images', 'fonts'] as $subFolder) {
            $folder = $logDir . '/' . $subFolder;
            if (!file_exists($folder)) {
                mkdir($folder, 0755, true);
            }
            recurse_copy(__DIR__ . '/template/' . $subFolder, $folder);
        }

        recurse_copy($this->templateDir . '/html_report/js', $logDir . '/js');
        recurse_copy($this->templateDir . '/html_report/css', $logDir . '/css');
        recurse_copy($this->templateDir . '/html_report/images', $logDir . '/images');
        recurse_copy($this->templateDir . '/html_report/fonts', $logDir . '/fonts');

        // render dynamic pages
        $this->renderPage($this->templateDir . '/html_report/index.php', $logDir . '/index.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/loc.php', $logDir . '/loc.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/relations.php', $logDir . '/relations.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/coupling.php', $logDir . '/coupling.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/all.php', $logDir . '/all.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/oop.php', $logDir . '/oop.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/complexity.php', $logDir . '/complexity.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/panel.php', $logDir . '/panel.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/violations.php', $logDir . '/violations.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/packages.php', $logDir . '/packages.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/package_relations.php', $logDir . '/package_relations.html', $consolidated, $history);
        $this->renderPage($this->templateDir . '/html_report/composer.php', $logDir . '/composer.html', $consolidated, $history);
        if ($this->config->has('git')) {
            $this->renderPage($this->templateDir . '/html_report/git.php', $logDir . '/git.html', $consolidated, $consolidatedGroups, $history);
        }
        $this->renderPage($this->templateDir . '/html_report/junit.php', $logDir . '/junit.html', $consolidated, $consolidatedGroups, $history);

        $today = (object)['avg' => $consolidated->getAvg(), 'sum' => $consolidated->getSum()];
        $encodedToday = json_encode($today, JSON_PRETTY_PRINT);
        $next = count($history) + 1;
        file_put_contents(sprintf('%s/js/history-%d.json', $logDir, $next), $encodedToday);
        file_put_contents(sprintf('%s/js/latest.json', $logDir), $encodedToday);

        file_put_contents(
            $logDir . '/classes.js',
            'var classes = ' . json_encode($consolidated->getClasses(), JSON_PRETTY_PRINT)
        );

        // HTML files to generate
        $filesToGenerate = [
            'index',
            'loc',
            'relations',
            'coupling',
            'all',
            'oop',
            'complexity',
            'panel',
            'violations',
            'packages',
            'package_relations',
            'composer',
        ];

        // consolidated by groups
        foreach ($consolidatedGroups as $name => $consolidated) {
            $outDir = $logDir . DIRECTORY_SEPARATOR . $name;
            $this->currentGroup = $name;
            $this->assetPath = '../';

            if (!file_exists($outDir)) {
                mkdir($outDir, 0755, true);
            }

            foreach ($filesToGenerate as $filename) {
                $this->renderPage(
                    sprintf('%s/html_report/%s.php', $this->templateDir, $filename),
                    sprintf('%s/%s.html', $outDir, $filename),
                    $consolidated,
                    $history
                );

                file_put_contents(
                    $outDir . '/classes.js',
                    'var classes = ' . json_encode($consolidated->getClasses(), JSON_PRETTY_PRINT)
                );
            }
        }

        $this->output->writeln(sprintf('HTML report generated in "%s" directory', $logDir));
    }

    /**
     * @param $source
     * @param $destination
     * @param Consolidated $consolidated
     * @param $history
     * @return $this
     */
    public function renderPage($source, $destination, Consolidated $consolidated, $history)
    {
        $this->sum = $sum = $consolidated->getSum();
        $this->avg = $avg = $consolidated->getAvg();
        $this->classes = $classes = $consolidated->getClasses();
        $this->files = $files = $consolidated->getFiles();
        $this->project = $project = $consolidated->getProject();
        $this->packages = $packages = $consolidated->getPackages();
        $config = $this->config;
        $this->history = $history;

        ob_start();
        require $source;
        $content = ob_get_clean();
        file_put_contents($destination, $content);
        return $this;
    }

    /**
     * @param $type
     * @param $key
     * @param bool $lowIsBetter
     * @param bool $highIsBetter
     * @return string
     */
    protected function getTrend($type, $key, $lowIsBetter = false, $highIsBetter = false)
    {
        if (!$this->isHomePage()) {
            return '';
        }

        $svg = [
            'gt' => '<svg fill="#000000" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
    <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/>
    <path d="M0 0h24v24H0z" fill="none"/>
</svg>',
            'eq' => '<svg fill="#000000" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
    <path d="M22 12l-4-4v3H3v2h15v3z"/>
    <path d="M0 0h24v24H0z" fill="none"/>
</svg>',
            'lt' => '<svg fill="#000000" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
    <path d="M16 18l2.29-2.29-4.88-4.88-4 4L2 7.41 3.41 6l6 6 4-4 6.3 6.29L22 12v6z"/>
    <path d="M0 0h24v24H0z" fill="none"/>
</svg>',
        ];
        $last = end($this->history);
        if (!isset($last->$type->$key)) {
            return '';
        }

        $oldValue = $last->$type->$key;
        $newValue = isset($this->$type->$key) ? $this->$type->$key : 0;

        $diff = $newValue - $oldValue;
        $goodOrBad = 'neutral';
        if ($newValue > $oldValue) {
            $r = 'gt';
            $diff = '+' . $diff;
            $goodOrBad = $lowIsBetter ? 'bad' : $goodOrBad;
            $goodOrBad = $highIsBetter ? 'good' : $goodOrBad;
        } elseif ($newValue < $oldValue) {
            $r = 'lt';
            $goodOrBad = $lowIsBetter ? 'good' : $goodOrBad;
            $goodOrBad = $highIsBetter ? 'bad' : $goodOrBad;
        } else {
            $r = 'eq';
        }

        return sprintf(
            '<span title="Last value: %s" class="progress progress-%s progress-%s">%s %s</span>',
            $oldValue,
            $goodOrBad,
            $r,
            $diff,
            $svg[$r]
        );
    }

    /**
     * @return bool
     */
    private function isHomePage()
    {
        return null === $this->currentGroup;
    }
}
