# Local CI image for local_corolair.
#
# Mirrors what .github/workflows/moodle-plugin-ci.yml does on ubuntu-22.04, so a
# local `make ci` runs the same checks GitHub does. Built on Moodle HQ's own PHP
# image, which already carries every extension Moodle requires (mysqli, pgsql, gd,
# intl, soap, zip, sodium, xmlrpc, opcache...).
#
# Build with a different PHP to reproduce the other half of the CI matrix:
#   docker compose build --build-arg PHP_VERSION=8.1
ARG PHP_VERSION=8.2
FROM moodlehq/moodle-php-apache:${PHP_VERSION}

ENV DEBIAN_FRONTEND=noninteractive

# A JRE is required by the mustache step: moodle-plugin-ci validates templates with
# vnu.jar (see MustacheCommand, --validator=<jar>), not the hosted validator.nu
# service. GitHub's ubuntu runners ship Java, so CI never had to install it.
RUN apt-get update \
    && apt-get install -y --no-install-recommends default-jre-headless \
    && rm -rf /var/lib/apt/lists/* \
    && java -version

# Moodle's PHPUnit bootstrap expects this locale; CI generates it explicitly.
RUN echo "en_AU.UTF-8 UTF-8" >> /etc/locale.gen && locale-gen

# The base image ships PHP and git but no composer.
RUN curl -sSLf https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm /tmp/composer-setup.php \
    && composer --version

# Node is required before moodle-plugin-ci is installed: its composer
# post-install-cmd runs `npm install` inside moodle-local_ci and the whole
# create-project aborts with 127 if npm is missing. Installed from the official
# tarball so the version is pinned and no apt repo key is needed.
ARG NODE_VERSION=24.19.0
RUN set -eux; \
    case "$(dpkg --print-architecture)" in \
        amd64) nodearch='x64' ;; \
        arm64) nodearch='arm64' ;; \
        *) echo "unsupported architecture: $(dpkg --print-architecture)" >&2; exit 1 ;; \
    esac; \
    curl -sSLf "https://nodejs.org/dist/v${NODE_VERSION}/node-v${NODE_VERSION}-linux-${nodearch}.tar.xz" \
        | tar -xJ -C /usr/local --strip-components=1 \
            --exclude='*/CHANGELOG.md' --exclude='*/LICENSE' --exclude='*/README.md'; \
    node --version; \
    npm --version

# nvm as well, so moodle-plugin-ci can switch to the Node version in Moodle's
# .nvmrc for the grunt and mustache steps. CI sets NVM_DIR for the same reason.
ENV NVM_DIR=/root/.nvm
RUN mkdir -p "$NVM_DIR" \
    && curl -sSLf https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.3/install.sh | bash

# Same package and constraint as the CI workflow.
RUN composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci /opt/ci '^4'
ENV PATH=/opt/ci/bin:/opt/ci/vendor/bin:$PATH

# Moodle is cloned here by `make setup`. Kept on a named volume rather than the
# bind mount: a Moodle tree on a macOS bind mount makes PHPUnit crawl.
WORKDIR /workspace
