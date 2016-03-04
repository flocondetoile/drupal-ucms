<?php

namespace MakinaCorpus\Ucms\Site;

use Drupal\Core\Entity\EntityManager;
use Drupal\node\NodeInterface;

/**
 * Handles whatever needs to be done with nodes
 *
 * @todo unit test
 */
class NodeDispatcher
{
    /**
     * @var \DatabaseConnection
     */
    private $db;

    /**
     * @var SiteManager
     */
    private $manager;

    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * Default constructor
     *
     * @param SiteManager $manager
     */
    public function __construct(\DatabaseConnection $db, SiteManager $manager, EntityManager $entityManager)
    {
        $this->db = $db;
        $this->manager = $manager;
        $this->entityManager = $entityManager;
    }

    /**
     * Reference node into a site
     *
     * @param Site $site
     * @param NodeInterface $node
     */
    public function createReference(Site $site, NodeInterface $node)
    {
        $this
            ->db
            ->merge('ucms_site_node')
            ->key(['nid' => $node->id(), 'site_id' => $site->id])
            ->execute()
        ;

        if (!in_array($site->id, $node->ucms_sites)) {
            $node->ucms_sites[] = $site->id;
        }
    }

    /**
     * Unreference node for a site
     *
     * @param Site $site
     * @param int[] $nodeIdList
     */
    public function deleteReferenceBulk(Site $site, $nodeIdList)
    {
        $this
            ->db
            ->delete('ucms_site_node')
            ->condition('nid', $nodeIdList)
            ->condition('site_id', $site->id)
            ->execute()
        ;

        $this
            ->entityManager
            ->getStorage('node')
            ->resetCache($nodeIdList)
        ;
    }

    /**
     * Considering the node as being a reference of another node, this function
     * will create a clone into database, and 
     *
     * @param NodeInterface $node
     *   The node to clone
     * @param Site $site
     *   Node might be cloned as global, case in which it should be attached to
     *   any site, just pass null here and here it goes
     *
     * @return NodeInterface
     */
    public function copyOnWrite(NodeInterface $node, Site $site = null, $keepOrigin = true)
    {
        // This instead of the clone operator will actually drop all existing
        // references and pointers and give you raw values, all credits to
        //   https://stackoverflow.com/a/10831885/5826569
        $clone = serialize(unserialize($node));
        $clone->parent_nid = $node->nid;
        $clone->origin_nid = empty($node->origin_nid) ? $node->id() : $node->origin_nid;

        if (!$site) {
            // This explicitely prevents the onPreInsert() method to attach the
            // node to current context whenever and keep it global
            $clone->site_id = false;
            $clone->is_global = 1;
        } else {
            $clone->site_id = $site->id;
            $clone->is_global = 0;
        }

        $this->entityManager->getStorage('node')->save($node);

        return $clone;
    }

    /**
     * Find candidate sites for referencing this node
     *
     * @param NodeInterface $node
     * @param int $userId
     *
     * @return Site[]
     */
    public function findSiteCandidates(NodeInterface $node, $userId)
    {
        $ne = $this
            ->db
            ->select('ucms_site_node', 'sn')
            ->where("sn.site_id = sa.site_id")
            ->condition('sn.nid', $node->id())
        ;
        $ne->addExpression('1');

        $idList = $this
            ->db
            ->select('ucms_site_access', 'sa')
            ->fields('sa', ['site_id'])
            ->notExists($ne)
            ->groupBy('sa.site_id')
            ->execute()
            ->fetchCol()
        ;

        return $this->manager->getStorage()->loadAll($idList);
    }

    /**
     * Clone the given source site content into the given target
     *
     * Most of all content will be just referenced, and compositions will be
     * cloned, which will allow us to do this in 2 SQL queries (easy right?)
     * nevertheless, a few content types might need cloning anyway, but we'll
     * worry about those later.
     *
     * Don't forget, this needs to run in a transaction.
     *
     * @param Site $source
     * @param Site $target
     */
    public function cloneSite(Site $source, Site $target)
    {
        // IMPORTANT: Read the documentation in Resources/docs/site-clone.sql
        // and UPDATE IT whenever you fix this.

        // First copy content references
        $this
            ->db
            ->query("
                INSERT INTO {ucms_site_node} (site_id, nid)
                SELECT
                    :target, usn.nid
                FROM {ucms_site_node} usn
                JOIN {node} n
                WHERE
                    usn.site_id = :source
                    AND n.status = 1
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {ucms_site_node} s_usn
                        WHERE
                            s_usn.nid = usn.nid
                            AND s_usn.site_id = :target2
                    )
            ", [
                ':target'   => $target->getId(),
                ':source'   => $source->getId(),
                ':target2'  => $target->getId(),
            ])
        ;

        // Then copy node layouts
        $this
            ->db
            ->query("
                INSERT INTO {ucms_layout} (site_id, nid)
                SELECT
                    :target, usn.nid
                FROM {ucms_layout} ul
                JOIN {ucms_site_node} usn ON
                    usn.nid = usn.nid
                    AND usn.site_id = :target
                WHERE
                    ul.site_id = :source
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {ucms_layout} s_ul
                        WHERE
                            s_ul.nid = ul.nid
                            AND s_ul.site_id = :target3
                    )
            ", [
                ':target'   => $target->getId(),
                ':target2'  => $target->getId(),
                ':source'   => $source->getId(),
                ':target3'  => $target->getId(),
            ])
        ;

        // Finally duplicate layout data
        $this
            ->db
            ->query("
                INSERT INTO {ucms_layout_data}
                    (layout_id, region, nid, weight, view_mode)
                SELECT
                    target_ul.id,
                    uld.region,
                    uld.nid,
                    uld.weight,
                    uld.view_mode
                FROM {ucms_layout} source_ul
                JOIN {ucms_layout_data} uld ON
                    source_ul.nid = uld.nid
                    AND source_ul.site_id = :source
                JOIN {node} n ON n.nid = uld.nid
                JOIN {ucms_layout} target_ul ON
                    target_ul.nid = uld.nid
                    AND target_ul.site_id = :target
                WHERE
                    n.status = 1
            ", [
                ':source'   => $source->getId(),
                ':target'   => $target->getId(),
            ])
        ;

        // @todo
        //   - duplicate menus
        //   - duplicate composite contents
    }

    public function onLoad($nodes)
    {
        // Attach site identifiers list to each node being loaded. Althought it does
        // and extra SQL query, this being the core of the whole site business, we
        // can't bypass this step nor risk it being stalled with unsynchronized data
        // in cache.

        // @todo Later in the future, determiner an efficient way of caching it,
        // we'll need this data to be set in Elastic Search anyway so we'll risk data
        // stalling in there.

        $r = $this
            ->db
            ->select('ucms_site_node', 'usn')
            ->fields('usn', ['nid', 'site_id'])
            ->condition('usn.nid', array_keys($nodes))
            ->orderBy('usn.nid')
            ->execute()
        ;

        foreach ($nodes as $node) {
            $node->ucms_sites = [];
        }

        foreach ($r as $row) {
            $node = $nodes[$row->nid];
            $node->ucms_sites[] = $row->site_id;
        }
    }

    public function onPreInsert(NodeInterface $node)
    {
        if (!property_exists($node, 'ucms_sites')) {
            $node->ucms_sites = [];
        }

        if (!property_exists($node, 'site_id')) {
            $node->site_id = null;
        }

        // If the node is created in a specific site context, then gives the
        // node ownership to this site, note this might change in the end, it
        // is mostly the node original site.
        if (!empty($node->site_id)) {
            $node->ucms_sites[] = $node->site_id;
            $node->is_global = 0;
        } else if (false !== $node->site_id) {
            if ($site = $this->manager->getContext()) {
                $node->site_id = $site->id;
                $node->ucms_sites[] = $site->id;
                $node->is_global = 0;
            } else {
                $node->is_global = 1;
            }
        } else {
            $node->is_global = 1;
        }
    }

    public function onPreUpdate(NodeInterface $node)
    {
    }

    public function onPreSave(NodeInterface $node)
    {
    }

    public function onInsert(NodeInterface $node)
    {
        if (!empty($node->site_id)) {
            $site = $this->manager->getStorage()->findOne($node->site_id);

            $this->createReference($site, $node);

            // Site might not have an homepage, because the factory wasn't
            // configured properly before creation, so just set this node
            // as home page.
            if (empty($site->home_nid)) {
                $site->home_nid = $node->nid;
                $this->manager->getStorage()->save($site, ['home_nid']);
            }
        }
    }

    public function onUpdate(NodeInterface $node)
    {
    }

    public function onSave(NodeInterface $node)
    {
        $sites = $this->manager->getStorage()->loadAll($node->ucms_sites);

        foreach ($sites as $site) {
            $this->createReference($site, $node);
        }
    }

    public function onDelete(NodeInterface $node)
    {
        // We do not need to delete from the {ucms_site_node} table since it's
        // being done by ON DELETE CASCADE deferred constraints
    }
}
