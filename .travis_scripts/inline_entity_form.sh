#!/bin/bash

# Add statement to see that this is running in Travis CI.
echo "running travis/inline_entity_form.sh"

set -e $DRUPAL_TI_DEBUG

# Ensure the right Drupal version is installed.
# The first time this is run, it will install Drupal.
# Note: This function is re-entrant.
drupal_ti_ensure_drupal

# Change to the Drupal module directory
cd "$DRUPAL_TI_DRUPAL_DIR/modules"

# Manually download dev verison of inline_entity_form.
git clone --branch 8.x-1.x https://git.drupal.org/project/inline_entity_form.git
