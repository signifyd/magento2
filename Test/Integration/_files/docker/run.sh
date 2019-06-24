#/bin/bash

echo "CLEANING DOCKER ENVIRONMENTS"

cd docker-environments

for ENVIRONMENT in *; do
    if [ -d "$ENVIRONMENT" ]; then
        cd $ENVIRONMENT
        docker-compose rm -f -s
        cd ..
    fi
done

cd ..



echo "PREPARING ENVIRONMENT"

# Cleaning up environment
rm -rf docker-environments
rm -rf magento-2-docker

# Setting up environment
mkdir docker-environments
git clone https://cvatca@bitbucket.org/cvatca/magento-2-docker.git
cp -R global/* magento-2-docker/



echo "CREATING TESTS DOCKER ENVIRONMENTS"

cd tests

for TEST in *; do
    if [ -d "$TEST" ]; then
        cp -R ../magento-2-docker ../docker-environments/test-install-$TEST
        cp -R $TEST/* ../docker-environments/test-install-$TEST 2> /dev/null
        cp $TEST/.env ../docker-environments/test-install-$TEST
    fi
done



echo "STARTTING TESTS"

cd ../docker-environments

for ENVIRONMENT in *; do
    if [ -d "$ENVIRONMENT" ]; then
        cd $ENVIRONMENT

        mkdir logs

        echo "BUILDING DOCKER CONTAINERS"
        docker-compose build --no-cache > logs/docker-compose-build.log

        echo "GETTING CONTAINERS UP"
        docker-compose up -d > logs/docker-compose-up.log

        CONTAINER_ID=`docker ps --filter "name=run-php" -q`

        echo "RUNNING start.sh ON CONTAINER $CONTAINER_ID"
        docker exec -ti $CONTAINER_ID /bin/bash /home/root/start.sh > logs/docker-exec.log

        if grep -q "Installation completed successfully" "web/var/log/signifyd_connect_install.log"; then
            echo "*** SIGNIFYD SUCCESSFULLY INSTALLED ***";
        fi

        echo "STOPPING CONTAINERS"
        docker-compose stop

        cd ..
    fi
done



echo "

OVERALL TESTS RESULTS
=====================
";

for ENVIRONMENT in *; do
    if [ -d "$ENVIRONMENT" ]; then
        cd $ENVIRONMENT

        if grep -q "Installation completed successfully" "web/var/log/signifyd_connect_install.log" 2> /dev/null; then
            echo "$ENVIRONMENT => SUCCESS";
        else
            echo "$ENVIRONMENT => FAILURE";
        fi

        cd ..
    fi
done

