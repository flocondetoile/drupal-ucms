<?php


namespace MakinaCorpus\Ucms\Label\Action;

use Drupal\Core\StringTranslation\StringTranslationTrait;

use MakinaCorpus\Ucms\Dashboard\Action\Action;
use MakinaCorpus\Ucms\Dashboard\Action\ActionProviderInterface;
use MakinaCorpus\Ucms\Label\LabelAccess;
use MakinaCorpus\Ucms\Label\LabelManager;


class LabelActionProvider implements ActionProviderInterface
{
    use StringTranslationTrait;


    /**
     * @var SiteManager
     */
    private $manager;


    /**
     * Default constructor
     *
     * @param LabelManager $manager
     */
    public function __construct(LabelManager $manager)
    {
        $this->manager = $manager;
    }


    /**
     * {inheritdoc}
     */
    public function getActions($item)
    {
        $actions = [];

        if ($this->manager->canEditLabel($item)) {
            $actions[] = new Action($this->t("Edit"), 'admin/dashboard/label/' . $item->tid . '/edit', 'dialog', 'pencil', -10, true, true);
            $actions[] = new Action($this->t("Delete"), 'admin/dashboard/label/' . $item->tid . '/delete', 'dialog', 'trash', -5, true, true, $this->manager->hasChildren($item));
        }

        return $actions;
    }


    /**
     * {inheritdoc}
     */
    public function supports($item)
    {
        return (
            isset($item->vocabulary_machine_name) &&
            ($item->vocabulary_machine_name === $this->manager->getVocabularyMachineName())
        );
    }
}

