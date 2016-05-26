<?php

namespace MakinaCorpus\Ucms\Site\Action;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

use MakinaCorpus\Ucms\Dashboard\Action\Action;
use MakinaCorpus\Ucms\Dashboard\Action\ActionProviderInterface;
use MakinaCorpus\Ucms\Site\NodeAccessService;
use MakinaCorpus\Ucms\Site\Site;

/**
 * The site module will add node actions, corresponding to reference
 * and cloning operations
 */
class NodeActionProvider implements ActionProviderInterface
{
    use StringTranslationTrait;

    /**
     * @var NodeAccessService
     */
    private $nodeAccess;

    /**
     * @var AccountInterface
     */
    private $currentUser;

    /**
     * Default constructor
     *
     * @param NodeAccessService $nodeAccess
     */
    public function __construct(NodeAccessService $nodeAccess, AccountInterface $currentUser)
    {
        $this->currentUser = $currentUser;
        $this->nodeAccess = $nodeAccess;
    }

    /**
     * {inheritdoc}
     */
    public function getActions($item)
    {
        $ret = [];

        /* @var $item NodeInterface */
        $account = $this->currentUser;

        if ($this->nodeAccess->userCanReference($account, $item)) {
            $ret[] = new Action($this->t("Use on my site"), 'node/' . $item->nid . '/reference', 'dialog', 'download-alt', 2, true, true, false, 'site');
        }
        if ($this->nodeAccess->userCanLock($account, $item)) {
            if ($item->is_clonable) {
                $ret[] = new Action($this->t("Lock"), 'node/' . $item->id() . '/lock', 'dialog', 'lock', 2, false, true, false, 'edit');
            } else {
                $ret[] = new Action($this->t("Unlock"), 'node/' . $item->id() . '/unlock', 'dialog', 'lock', 2, false, true, false, 'edit');
            }
        }

        $ret[] = new Action($this->t("View in site"), 'node/' . $item->id() . '/site-list', 'dialog', 'search', 100, false, true, false, 'view');

        /*
         if ($item->access('clone')) {
         $ret[] = new Action($this->t("Clone"), 'node/' . $item->nid . '/clone', null, 'dialog', 'save', 0, false, true);
         }
         if (!empty($item->is_clonable)) {
         // ajouter au panier  permet d'ajouter le contenu au panier de l'utilisateur courant ;
         // enlever du panier  permet d'enlever le contenu du panier de l'utilisateur courant ;
         }
         */

        return $ret;
    }

    /**
     * {inheritdoc}
     */
    public function supports($item)
    {
        return $item instanceof NodeInterface;
    }
}
