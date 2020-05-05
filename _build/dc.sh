#!/usr/bin/env bash

# Proxy script to run docker-compose.

# Set up the vars depending on the current OS.
FIXUID=0 && [ "${OSTYPE:0:5}" == 'linux' ] && FIXUID=1
DOCKER_RUN_USER='' && [ "${OSTYPE:0:5}" == 'linux' ] && DOCKER_RUN_USER=$(id -u)
DOCKER_RUN_GROUP='' && [ "${OSTYPE:0:5}" == 'linux' ] && DOCKER_RUN_GROUP=$(id -g)

export FIXUID
export DOCKER_RUN_USER
export DOCKER_RUN_GROUP

# By default it should use the root docker-compose.yml file.
docker-compose -f docker-compose.yml $@
