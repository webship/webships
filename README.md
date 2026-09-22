# Webships

The Webships installer: an installation profile that asks for an API
site template, applies it, and gets out of the way. It enables no
modules of its own; the site template you choose decides what the site
has.


## Table of contents

- Features
- Requirements
- Installation
- Configuration
- Usage


## Features

- A "Choose site template" step in the Drupal installer, between
  setting up the database and configuring the site.
- Two curated API site templates, listed in `site-templates.yml`:
  - [Webships Starter](https://www.drupal.org/project/webships_starter)
    (`drupal/webships_starter`): a web apps gallery with organizations,
    served over JSON:API.
  - [WebAPI Starter](https://www.drupal.org/project/webapi_starter)
    (`drupal/webapi_starter`): a read-only JSON:API from `/api` with
    OAuth 2.0 and Swagger UI documentation.
- Any recipe of `type: Site` in the project's recipes directory is
  offered too, and wins over the curated entry of the same name.
- A curated site template that is not in the code base yet is required
  with Composer during the install.
- The administrator role recipe from core is always applied.
- The profile uninstalls itself when the install is done.


## Requirements

Drupal core `^11.4`. The profile also declares `^12`, but the site
templates depend on contributed modules that have no Drupal 12 release
yet, so build on Drupal 11.4 for now.

To let the installer require a site template with Composer, the project
root must be writable by the web server. Otherwise only the site
templates already in the recipes directory are offered.


## Installation

Start from the
[Webships Project](https://www.drupal.org/project/webships_project)
template. It requires this profile and both site templates, so nothing
is downloaded during the install:

```
mkdir myapi && cd myapi
ddev config --project-type=drupal11 --docroot=web
ddev composer create-project drupal/webships_project
ddev start
```

On an existing Composer project, require the profile. Its recipes
directory comes from the `type:drupal-recipe` installer path:

```
composer require webship/webships
```


## Configuration

To limit or replace the list of site templates, convert
`site-templates.yml` to a PHP array (`<?php return [...];`) and save it
as `sites/default/site-templates.php`. Entries can point at a local
directory with a `path` key instead of a `package`.


## Usage

In the browser, open the site and follow the installer: choose a site
template, then set up the site name and the administrator account.

From the command line, name the site template with the form ID of the
template step. Without it, Webships Starter is chosen:

```
ddev drush site:install webships \
  installer_site_template_form.add_ons=webapi_starter \
  --account-name=webmaster -y
```
