#!/bin/bash

echo `date` >all_instances_all_regions.txt

echo "Fetching AWS Regions..."
regions=($(aws ec2 describe-regions --output text | cut -f4))

# Loop over regions
for region in ${regions[@]}; do
  echo "Getting instances in $region..."
  aws ec2 describe-instances \
    --region $region \
    --query "Reservations[*].Instances[*].{Name:Tags[?Key==\`Name\`]|[0].Value,AZ:Placement.AvailabilityZone,KeyName:KeyName,IP:PublicIpAddress,State:State.Name}" \
    --output text >>all_instances_all_regions.txt
  sleep 1
done
