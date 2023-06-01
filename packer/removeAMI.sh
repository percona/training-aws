#!/usr/bin/env bash

listamis() {
	echo "No AMI provided. Here is a list of training AMIs in the $REGION region:"
	aws ec2 describe-images --region $REGION \
		--filters "Name=name,Values=*Training*" \
		--output text \
		--owners self \
		--query 'Images[*].[Name, ImageId]'
}

if [ "x$1" == "x" ]; then
  echo "provide region"
  exit 1
fi
REGION=$1

if [ "x$2" == "x" ]; then
	listamis
	exit 1
fi
AMI_ID=$2

temp_snapshot_id=
my_array=( $(aws ec2 describe-images --image-ids $AMI_ID --region $REGION --output text --query "Images[*].BlockDeviceMappings[*].Ebs.SnapshotId"))
my_array_length=${#my_array[@]}

echo "Deregistering AMI: $AMI_ID"
aws ec2 deregister-image --image-id $AMI_ID --region $REGION

echo "Removing Snapshot"
for (( i=0; i<$my_array_length; i++ ))
do
	temp_snapshot_id=${my_array[$i]}
	echo "Deleting Snapshot: $temp_snapshot_id"
	aws ec2 delete-snapshot --snapshot-id $temp_snapshot_id --region $REGION
done

echo "** Successfully removed AMI and associated EBS snapshots"
