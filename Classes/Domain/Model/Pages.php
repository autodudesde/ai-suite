<?php

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Domain\Model;

class Pages
{
    protected int $pid;
    protected string $title;
    protected int $doktype;
    protected int $hidden;
    protected int $deleted;
    protected int $navHide;
    protected string $seoTitle;
    protected string $description;
    protected string $slug;
    protected string $txAiSuiteTopiclist;

    protected int $tstamp;
    protected int $permsUserid;
    protected int $permsGroupid;
    protected int $permsUser;
    protected int $permsGroup;
    protected int $permsEverybody;
    protected int $isSiteroot;

    public function __construct(
        ?int $pid,
        string $title,
        int $doktype,
        int $hidden,
        int $deleted,
        int $navHide,
        string $seoTitle,
        string $description,
        string $slug,
        string $txAiSuiteTopiclist,
        int $tstamp,
        int $permsUserid,
        int $permsGroupid,
        int $permsUser,
        int $permsGroup,
        int $permsEverybody,
        int $isSiteroot = 0
    ) {
        $this->pid = $pid;
        $this->title = trim($title);
        $this->doktype = $doktype;
        $this->hidden = $hidden;
        $this->deleted = $deleted;
        $this->navHide = $navHide;
        $this->seoTitle = trim($seoTitle);
        $this->description = trim($description);
        $this->slug = trim($slug);
        $this->txAiSuiteTopiclist = trim($txAiSuiteTopiclist);
        $this->tstamp = $tstamp;
        $this->permsUserid = $permsUserid;
        $this->permsGroupid = $permsGroupid;
        $this->permsUser = $permsUser;
        $this->permsGroup = $permsGroup;
        $this->permsEverybody = $permsEverybody;
        $this->isSiteroot = $isSiteroot;
    }

    public static function createEmpty(): self
    {
        return new self(
            1, // pid
            '', // title
            1, // doktype
            0, // hidden
            0, // deleted
            0, // navHide
            '', // seoTitle
            '', // description
            '', // slug
            '', // txAiSuiteTopiclist
            0, // tstamp
            0, // permsUserid
            0, // permsGroupid
            0, // permsUser
            0, // permsGroup,
            0, // permsEverybody
            0, // isSiteroot
        );
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function setPid(int $pid): self
    {
        $this->pid = $pid;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);
        return $this;
    }

    public function getDoktype(): int
    {
        return $this->doktype;
    }

    public function setDoktype(int $doktype): self
    {
        $this->doktype = $doktype;
        return $this;
    }

    public function getHidden(): int
    {
        return $this->hidden;
    }

    public function setHidden(int $hidden): self
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function getDeleted(): int
    {
        return $this->deleted;
    }

    public function setDeleted(int $deleted): self
    {
        $this->deleted = $deleted;
        return $this;
    }

    public function getNavHide(): int
    {
        return $this->navHide;
    }

    public function setNavHide(int $navHide): self
    {
        $this->navHide = $navHide;
        return $this;
    }

    public function getSeoTitle(): string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(string $seoTitle): self
    {
        $this->seoTitle = trim($seoTitle);
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = trim($slug);
        return $this;
    }

    public function getTxAiSuiteTopiclist(): string
    {
        return $this->txAiSuiteTopiclist;
    }

    public function setTxAiSuiteTopiclist(string $txAiSuiteTopiclist): self
    {
        $this->txAiSuiteTopiclist = trim($txAiSuiteTopiclist);
        return $this;
    }

    public function getTstamp(): int
    {
        return $this->tstamp;
    }

    public function setTstamp(int $tstamp): self
    {
        $this->tstamp = $tstamp;
        return $this;
    }

    public function getPermsUserid(): int
    {
        return $this->permsUserid;
    }

    public function setPermsUserid(int $permsUserid): self
    {
        $this->permsUserid = $permsUserid;
        return $this;
    }

    public function getPermsGroupid(): int
    {
        return $this->permsGroupid;
    }

    public function setPermsGroupid(int $permsGroupid): self
    {
        $this->permsGroupid = $permsGroupid;
        return $this;
    }

    public function getPermsUser(): int
    {
        return $this->permsUser;
    }

    public function setPermsUser(int $permsUser): self
    {
        $this->permsUser = $permsUser;
        return $this;
    }

    public function getPermsGroup(): int
    {
        return $this->permsGroup;
    }

    public function setPermsGroup(int $permsGroup): self
    {
        $this->permsGroup = $permsGroup;
        return $this;
    }

    public function getPermsEverybody(): int
    {
        return $this->permsEverybody;
    }

    public function setPermsEverybody(int $permsEverybody): self
    {
        $this->permsEverybody = $permsEverybody;
        return $this;
    }

    public function getIsSiteroot(): int
    {
        return $this->isSiteroot;
    }

    public function setIsSiteroot(int $isSiteroot): void
    {
        $this->isSiteroot = $isSiteroot;
    }

    public function toDatabase(): array
    {
        return [
            'pid' => $this->pid,
            'title' => $this->title,
            'doktype' => $this->doktype,
            'hidden' => $this->hidden,
            'deleted' => $this->deleted,
            'nav_hide' => $this->navHide,
            'seo_title' => $this->seoTitle,
            'description' => $this->description,
            'slug' => $this->slug,
            'tstamp' => $this->tstamp,
            'perms_userid' => $this->permsUserid,
            'perms_groupid' => $this->permsGroupid,
            'perms_user' => $this->permsUser,
            'perms_group' => $this->permsGroup,
            'perms_everybody' => $this->permsEverybody,
            'is_siteroot' => $this->isSiteroot,
        ];
    }
}
