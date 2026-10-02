<?php

declare(strict_types=1);

/*
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 */

namespace AutoDudes\AiSuite\Domain\Model;

class Pages
{
    public function __construct(
        protected ?int $pid,
        protected string $title,
        protected int $doktype,
        protected int $hidden,
        protected int $deleted,
        protected int $navHide,
        protected string $seoTitle,
        protected string $description,
        protected string $slug,
        protected int $tstamp,
        protected int $permsUserid,
        protected int $permsGroupid,
        protected int $permsUser,
        protected int $permsGroup,
        protected int $permsEverybody,
        protected int $isSiteroot = 0,
    ) {
        $this->title = trim($title);
        $this->seoTitle = trim($seoTitle);
        $this->description = trim($description);
        $this->slug = trim($slug);
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
            0, // tstamp
            0, // permsUserid
            0, // permsGroupid
            0, // permsUser
            0, // permsGroup,
            0, // permsEverybody
            0, // isSiteroot
        );
    }

    public function getPid(): ?int
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

    public function setHidden(int $hidden): self
    {
        $this->hidden = $hidden;

        return $this;
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

    public function setIsSiteroot(int $isSiteroot): void
    {
        $this->isSiteroot = $isSiteroot;
    }

    /**
     * @return array<string, mixed>
     */
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
