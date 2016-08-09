<?php
namespace Hal\Metric;

class Consolidated
{
    /**
     * @var object
     */
    private $avg;

    /**
     * @var object
     */
    private $sum;

    /**
     * @var array
     */
    private $classes = [];

    /**
     * @var array
     */
    private $files = [];

    /**
     * @var array
     */
    private $project = [];

    /**
     * Consolided constructor.
     * @param Metrics $metrics
     */
    public function __construct(Metrics $metrics)
    {
        // grouping results
        $classes = [];
        $functions = [];
        $files = [];
        $project = [];
        $nbInterfaces = 0;
        foreach ($metrics->all() as $key => $item) {
            if ($item instanceof ClassMetric) {
                $classes[] = $item->all();;
            }
            if ($item instanceof InterfaceMetric) {
                $nbInterfaces++;
            }
            if ($item instanceof FunctionMetric) {
                $functions[$key] = $item->all();;
            }
            if ($item instanceof FileMetric) {
                $files[$key] = $item->all();;
            }
            if ($item instanceof ProjectMetric) {
                $project[$key] = $item->all();;
            }
        }

        // sums
        $sum = (object)[
            'loc' => 0,
            'cloc' => 0,
            'lloc' => 0,
            'nbMethods' => 0,
        ];
        $avg = (object)[
            'ccn' => [],
            'bugs' => [],
            'kanDefect' => [],
            'relativeSystemComplexity' => [],
            'relativeDataComplexity' => [],
            'relativeStructuralComplexity' => [],
            'volume' => [],
            'commentWeight' => [],
            'intelligentContent' => [],
            'lcom' => [],
            'instability' => [],
            'afferentCoupling' => [],
            'efferentCoupling' => [],
            'difficulty' => [],
            'lcom' => [],
        ];

        foreach ($metrics->all() as $key => $item) {
            $sum->loc += $item->get('loc');
            $sum->lloc += $item->get('lloc');
            $sum->cloc += $item->get('cloc');
            $sum->nbMethods += $item->get('nbMethods');

            foreach ($avg as $k => &$a) {
                array_push($avg->$k, $item->get($k));
            }
        }
        $sum->nbClasses = sizeof($classes) - $nbInterfaces;
        $sum->nbInterfaces = $nbInterfaces;

        foreach ($avg as &$a) {
            if (sizeof($a) > 0) {
                $a = round(array_sum($a) / sizeof($a), 2);
            } else {
                $a = 0;
            }
        }

        $this->avg = $avg;
        $this->sum = $sum;
        $this->classes = $classes;
        $this->files = $files;
        $this->project = $project;
    }

    /**
     * @return object
     */
    public function getAvg()
    {
        return $this->avg;
    }

    /**
     * @return object
     */
    public function getSum()
    {
        return $this->sum;
    }

    /**
     * @return array
     */
    public function getClasses()
    {
        return $this->classes;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @return array
     */
    public function getProject()
    {
        return $this->project;
    }
}
