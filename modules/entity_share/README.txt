CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Field types
 * Requirements
 * Recommended modules
 * Similar modules
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

This module allows to share entities using the JSON:API. It provides an UI to
use the endpoints provided by the JSON:API module.

You can define one website as a "server" and another website as a "client". A
website can be both "client" and "server".

Currently you can only, on the client website, get content exposed from the
server website. You can't push content from the client website to the server
website.

When pulling content, referenced entities are also pulled recursively. If you
reselect a content it will be updated and referenced entities will also be
updated recursively.

The entities you want to share must have the same structure (machine name, field
machine name and storage) across the different websites.

Note about links and embed entities in RTE:

To ensure the share of links referencing entities (most of the time content) and
entities that are embedded in RTE, we recommend to use the following modules, as
they use UUID to operate:
 * Linkit (https://www.drupal.org/project/linkit)
 * Entity Embed (https://www.drupal.org/project/entity_embed)

This module does nothing to ensure the embed entities are shared automatically,
you must share the entities by yourself.

Note about path and Pathauto:

To expose the information if a content entity has its path managed by Pathauto,
Entity Share provides a field enhancer plugin to enable on the server website.

Note about multilingual content:

When pulling translatable content, the default langcode is dropped to avoid to
have to pull the content in its original language and because the original
language may be not enabled on the client website.

Referenced entities will be imported in the same language as the referencing
entity if possible. If a referenced entity is not available in the same
language, Drupal will display the entity in the first language available
depending of the languages weight.

Note about CRON usage:

If you want to synchronize entities automatically using CRON:
 * the Entity Share Cron (https://www.drupal.org/project/entity_share_cron)
   module provides an UI to import channels using Cron.

Note about Entity Share Client module:

As the Entity Share Client sub-module has a dependency on the JSON:API module,
on your client website, content and configuration will be exposed in JSON:API
endpoints, by default on /jsonapi. As the JSON:API use the Drupal access API to
check access to entities, if you have used the access API and permission system
correctly, users will not have access to content they should not access. But for
example, they will be able to access fields not displayed in view modes.

So to add a new security layer, it is advised to block requests on JSON:API
endpoints on your client website (and also if needed or possible on your server
website). This configuration can be done in your webserver configuration to
block external requests and only authorized requests coming from internal
networks or trusted IPs.

This configuration will differ based on the webserver you are using (Apache,
Nginx, Microsoft IIS, etc.) and also based on your network structure, for
example if you have a cache server (Varnish or other) or load balancer (Nginx,
HAProxy, etc.).

Note about full pager feature:

On the pull form, you can get a full pagination if on the server side, the
JSON:API Extras module is enabled and if the configuration "Include count in
collection queries" is enabled on JSON:API Extras settings form.

Limitation:

Currently we do not handle config entities and user entities to avoid side
effects.

FIELD TYPES
-----------

Supported:
 * Block (with JSON:API Extras, see troubleshooting section)
 * Boolean
 * Content (entity reference)
 * Date:
  - date only
  - date and time
 * Date range:
  - date only
  - date all day
  - date and time
 * Email
 * File
 * Image
 * Link:
  - internal (with JSON:API Extras, see troubleshooting section)
  - external
 * List:
  - float
  - integer
  - text
 * Media:
  - audio
  - image
  - file
  - remote video
  - video
 * Metatags (with JSON:API Extras, see troubleshooting section)
 * Number:
  - decimal
  - float
  - integer
 * Paragraphs
 * Taxonomy
 * Telephone
 * Text:
  - plain
  - plain, long
  - formatted
  - formatted, long
  - formatted, long, with summary
 * Timestamp

Not supported:
 * Comment: See https://www.drupal.org/project/jsonapi_comment
 * Dynamic entity reference: See https://www.drupal.org/project/entity_share/issues/3056102

REQUIREMENTS
------------

This module requires the following modules:
 * JSON:API (https://www.drupal.org/project/jsonapi)


RECOMMENDED MODULES
-------------------

 * JSON:API Extras (https://www.drupal.org/project/jsonapi_extras):
   To allow to customize the JSON:API endpoints and to enable full pager
   feature. See the troubleshooting section about the link fields.
 * Diff (https://www.drupal.org/project/diff):
   To see a diff if entities are not synchronized. Note that the following patch
   needs to be applied on the diff module:
   https://www.drupal.org/project/diff/issues/3088274#comment-13312389


SIMILAR MODULES
---------------

 * Entity pilot (https://www.drupal.org/project/entity_pilot): Entity Share does
   not require any subscription to a service.


INSTALLATION
------------

 * Install and enable the Entity Share Server on the site you want to get
   content from.
 * Install and enable the Entity Share Client on the site you want to put
   content on.


CONFIGURATION
-------------

On the server website:
 * Enable the Entity Share Server module.
 * Optional: Prepare an user with the permission "Access channels list" if you
   do not want to use the admin user.
 * Go to the configuration page, Configuration > Web services > Entity Share >
   Channels (admin/config/services/entity_share/channel) and add at least one
   channel.

On the client website:
 * Enable the Entity Share Client module.
 * Go to the configuration page, Configuration > Web services > Entity Share >
   Remote websites (admin/config/services/entity_share/remote) and create a
   remote website corresponding to your server website with the user name and
   password configured on the server website.
 * Go to the pull form, Content > Entity Share > Pull entities
   (admin/content/entity_share/pull), and select your remote website, the
   available channels will be listed and when selecting a channel, the entities
   exposed on this channel will be available to synchronize.


TROUBLESHOOTING
---------------

 * Block fields: To support Block fields, Entity Share provides a field
   enhancer plugin to enable on the server website. It will allow to import
   block content automatically.
 * Internal link fields: As Drupal stores the id of entities for internal link
   fields that reference entities, we need Drupal to store the value of these
   fields using UUID. There is an issue for that
   https://www.drupal.org/node/2873068.
   As a workaround, it is possible to use the JSON:API Extras module to alter
   the data for link fields. for the concerned JSON:API endpoints, you can use
   the field enhancer "UUID for link (link field only)" on the link fields.
   Note 1: This configuration must be applied and identical on both websites
   (server and client).
   Note 2: If the target entity of a link field value has not been imported yet,
   the value of the link field will be unset. So an update will be required to
   update the link field value.
 * Metatag fields: To support Metatag fields, Entity Share provides a field
   enhancer plugin to enable on the server website.

MAINTAINERS
-----------

Current maintainers:
 * Thomas Bernard (ithom) - https://www.drupal.org/user/3175403
 * Florent Torregrosa (Grimreaper) - https://www.drupal.org/user/2388214

This project has been sponsored by:
 * Smile - https://www.drupal.org/smile
   Sponsored initial development, evolutions, maintainance and support.
 * Lullabot - https://www.drupal.org/lullabot
   Sponsored development of new features in association with
   https://www.drupal.org/carnegie-mellon-university
