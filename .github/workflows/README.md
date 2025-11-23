# Repository Workflow Configuration

## [`auto-merge.yml`](auto-merge.yml)

The workflow depends on a `WORKFLOW_TOKEN` dependabot secret. Create a [fine-grained token](https://github.com/settings/personal-access-tokens) with the following permissions:

* Metadata: read (required)
* Contents: read and write
* Pull requests: read and write

Then add this as the `WORKFLOW_TOKEN` to the repository's settings under **Security > Secrets and variables > Dependabot** (`./settings/secrets/dependabot`).

## [`deploy-dotorg.yml`](deploy-dotorg.yml)

This workflow is triggered when a new release tag is created.

The workflow requires two action secrets to be populated as required by [10up/action-wordpress-plugin-deploy](https://github.com/10up/action-wordpress-plugin-deploy): `SVN_USERNAME` and `SVN_PASSWORD`. These are the username and password for a WordPress.org user account which has commit access to the SVN repo. The SVN credentials can be located in your dotorg profile's [Account & Security](https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password) where the SVN password begins with "svn_".

Add these secrets to repository's settings under **Security > Secrets and variables > Actions** (`./settings/secrets/actions`).

## [`update-dotorg-assets.yml`](update-dotorg-assets.yml)

This workflow is triggered manually when accessing "Run workflow" from the workflow located on the Actions screen.

The workflow requires two action secrets to be populated as required by [10up/action-wordpress-plugin-asset-update](https://github.com/10up/action-wordpress-plugin-deploy): `SVN_USERNAME` and `SVN_PASSWORD`. See the `deploy-dotorg.yml` section above. 

